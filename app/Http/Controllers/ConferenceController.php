<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceStatus;
use App\Models\Conference;
use App\Services\AuditLogger;
use App\Services\ConferenceProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConferenceController extends Controller
{
    private const RESERVED_SLUGS = ['admin', 'api', 'dashboard', 'login', 'logout', 'forgot-password', 'reset-password', 'change-password', 'storage', 'submission', 'conferences', 'up'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Conference::class);
        $query = Conference::query()->withCount('submissions')->orderByDesc('created_at');
        if (! $request->user()->isSuperAdmin()) {
            $query->whereHas('memberships', fn ($membership) => $membership
                ->where('user_id', $request->user()->id)
                ->where('is_active', true));
        }

        return view('conferences.index', ['conferences' => $query->paginate(15)]);
    }

    public function create(): View
    {
        $this->authorize('create', Conference::class);

        return view('conferences.create');
    }

    public function store(Request $request, ConferenceProvisioner $provisioner, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('create', Conference::class);
        $validated = $this->validateConference($request);

        $settings = [];
        $settings['submission_mode'] = $request->input('submission_mode', 'paperflow_native');
        $validated['settings'] = $settings;

        $conference = $provisioner->create($validated, $request->user());
        $audit->record('conference.created', $conference, $conference, newValues: $validated);

        return redirect()->route('conferences.show', $conference)->with('success', 'Conference created successfully.');
    }

    public function show(Conference $conference): View
    {
        $this->authorize('view', $conference);
        $conference->loadCount('submissions')->load(['memberships.user']);
        $statusCounts = $conference->submissions()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('conferences.show', compact('conference', 'statusCounts'));
    }

    public function edit(Conference $conference): View
    {
        $this->authorize('update', $conference);

        return view('conferences.edit', compact('conference'));
    }

    public function update(Request $request, Conference $conference, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $conference);
        $validated = $this->validateConference($request, $conference);
        $settings = $conference->settings ?? [];
        $settings['submission_mode'] = $request->input('submission_mode', 'paperflow_native');
        if ($request->has('google_form_mapping')) {
            $mappingInput = (array) $request->input('google_form_mapping', []);
            $cleanMapping = [];
            foreach ($mappingInput as $k => $v) {
                if ($k === 'custom_fields' && is_array($v)) {
                    $cleanCustom = [];
                    foreach ($v as $cf) {
                        if (is_array($cf) && filled($cf['label'] ?? null) && filled($cf['column'] ?? null)) {
                            $cleanCustom[] = [
                                'label' => trim((string) $cf['label']),
                                'column' => trim((string) $cf['column']),
                            ];
                        }
                    }
                    $cleanMapping['custom_fields'] = $cleanCustom;
                } elseif (is_string($v)) {
                    $cleanMapping[$k] = trim($v);
                }
            }
            $settings['google_form_mapping'] = $cleanMapping;
        }
        $settings['allowed_extensions'] = $request->input('allowed_extensions', ['doc', 'docx', 'tex', 'zip']);
        $settings['max_file_mb'] = $request->integer('max_file_mb', 25);
        $settings['brand_primary'] = $request->input('brand_primary', '#102a43');
        $settings['brand_accent'] = $request->input('brand_accent', '#f47c20');
        $settings['brand_tagline'] = $request->input('brand_tagline');
        $settings['form_title'] = $request->input('form_title');
        $settings['form_description'] = $request->input('form_description');
        if ($request->hasFile('brand_logo')) {
            if (isset($settings['brand_logo'])) {
                Storage::disk('public')->delete($settings['brand_logo']);
            }
            $settings['brand_logo'] = $request->file('brand_logo')->store('conference-branding', 'public');
        }
        if ($request->hasFile('brand_banner')) {
            if (isset($settings['brand_banner'])) {
                Storage::disk('public')->delete($settings['brand_banner']);
            }
            $settings['brand_banner'] = $request->file('brand_banner')->store('conference-branding', 'public');
        }
        $validated['settings'] = $settings;
        $old = $conference->only(array_keys($validated));
        $conference->update($validated);
        $audit->record('conference.updated', $conference, $conference, $old, $validated);

        return redirect()->route('conferences.show', $conference)->with('success', 'Conference settings updated successfully.');
    }

    public function duplicate(Request $request, Conference $conference, ConferenceProvisioner $provisioner, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $conference);
        if ($request->has('slug')) {
            $request->merge(['slug' => Str::lower((string) $request->input('slug'))]);
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::notIn(self::RESERVED_SLUGS), Rule::unique('conferences', 'slug')],
        ]);
        $copy = $provisioner->duplicate($conference, $validated, $request->user());
        $audit->record('conference.duplicated', $copy, $copy, newValues: ['source_id' => $conference->id]);

        return redirect()->route('conferences.show', $copy)->with('success', 'Conference duplicated successfully with default forms and checklists.');
    }

    public function destroy(string $conferenceId, AuditLogger $audit): RedirectResponse
    {
        $conference = Conference::withTrashed()->find($conferenceId);
        if (! $conference) {
            return redirect()->route('conferences.index')->with('info', 'Conference has already been removed.');
        }

        if ($conference->trashed()) {
            return redirect()->route('conferences.index')->with('info', "Conference \"{$conference->name}\" was already deleted.");
        }

        $this->authorize('delete', $conference);

        $oldValues = $conference->only(['id', 'name', 'slug']);
        $name = $conference->name;

        if (session('active_conference_id') === $conference->id) {
            session()->forget('active_conference_id');
        }

        $audit->record('conference.deleted', $conference, $conference, oldValues: $oldValues);

        $conference->update(['slug' => $conference->slug . '-deleted-' . time()]);
        $conference->delete();

        return redirect()->route('conferences.index')->with('success', "Conference \"{$name}\" has been deleted successfully.");
    }

    /** @return array<string, mixed> */
    private function validateConference(Request $request, ?Conference $conference = null): array
    {
        if ($request->has('slug')) {
            $request->merge(['slug' => Str::lower((string) $request->input('slug'))]);
        }
        if (! $request->filled('submission_opens_at') && $request->filled('starts_at')) {
            $request->merge(['submission_opens_at' => $request->input('starts_at')]);
        }
        if (! $request->filled('submission_closes_at') && $request->filled('ends_at')) {
            $request->merge(['submission_closes_at' => $request->input('ends_at')]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::notIn(self::RESERVED_SLUGS), Rule::unique('conferences', 'slug')->ignore($conference)],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::enum(ConferenceStatus::class)],
            'timezone' => ['required', 'timezone:all'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'submission_opens_at' => ['nullable', 'date'],
            'submission_closes_at' => ['nullable', 'date', 'after_or_equal:submission_opens_at'],
            'allowed_extensions' => ['nullable', 'array'], 'allowed_extensions.*' => ['string', Rule::in(['doc', 'docx', 'tex', 'zip', 'pdf'])],
            'max_file_mb' => ['nullable', 'integer', 'min:1', 'max:100'],
            'brand_primary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'brand_accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_tagline' => ['nullable', 'string', 'max:255'], 'brand_logo' => ['nullable', 'image', 'max:2048'],
            'brand_banner' => ['nullable', 'image', 'max:4096'],
            'form_title' => ['nullable', 'string', 'max:500'],
            'form_description' => ['nullable', 'string', 'max:5000'],
            'submission_mode' => ['nullable', 'string', Rule::in(['paperflow_native', 'google_form_external'])],
            'google_form_mapping' => ['nullable', 'array'],
        ]);
        $validated['submission_opens_at'] = $validated['submission_opens_at'] ?? ($validated['starts_at'] ?? null);
        $validated['submission_closes_at'] = $validated['submission_closes_at'] ?? ($validated['ends_at'] ?? null);

        unset($validated['allowed_extensions'], $validated['max_file_mb'], $validated['brand_primary'], $validated['brand_accent'], $validated['brand_tagline'], $validated['brand_logo'], $validated['brand_banner'], $validated['form_title'], $validated['form_description'], $validated['submission_mode'], $validated['google_form_mapping']);

        return $validated;
    }
}
