<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceStatus;
use App\Models\Conference;
use App\Services\AuditLogger;
use App\Services\ConferenceProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $conference = $provisioner->create($validated, $request->user());
        $audit->record('conference.created', $conference, $conference, newValues: $validated);

        return redirect()->route('conferences.show', $conference)->with('success', 'Conference berhasil dibuat.');
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
        $old = $conference->only(array_keys($validated));
        $conference->update($validated);
        $audit->record('conference.updated', $conference, $conference, $old, $validated);

        return redirect()->route('conferences.show', $conference)->with('success', 'Pengaturan conference diperbarui.');
    }

    public function duplicate(Request $request, Conference $conference, ConferenceProvisioner $provisioner, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $conference);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::notIn(self::RESERVED_SLUGS), 'unique:conferences,slug'],
        ]);
        $validated['slug'] = Str::lower($validated['slug']);
        $copy = $provisioner->duplicate($conference, $validated, $request->user());
        $audit->record('conference.duplicated', $copy, $copy, newValues: ['source_id' => $conference->id]);

        return redirect()->route('conferences.show', $copy)->with('success', 'Conference beserta form dan checklist berhasil diduplikasi.');
    }

    /** @return array<string, mixed> */
    private function validateConference(Request $request, ?Conference $conference = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::notIn(self::RESERVED_SLUGS), Rule::unique('conferences')->ignore($conference)],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::enum(ConferenceStatus::class)],
            'timezone' => ['required', 'timezone:all'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'submission_opens_at' => ['nullable', 'date'],
            'submission_closes_at' => ['nullable', 'date', 'after_or_equal:submission_opens_at'],
        ]);
        $validated['slug'] = Str::lower($validated['slug']);

        return $validated;
    }
}
