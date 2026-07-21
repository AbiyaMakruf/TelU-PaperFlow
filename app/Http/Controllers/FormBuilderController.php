<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Models\FormVersion;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FormBuilderController extends Controller
{
    public function edit(Request $request, Conference $conference): View
    {
        $this->authorize('update', $conference);
        $form = $this->draftFor($conference, $request);

        return view('conferences.form-builder', compact('conference', 'form'));
    }

    public function update(Request $request, Conference $conference, FormVersion $form, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $conference);
        abort_unless($form->conference_id === $conference->id && $form->status === 'draft', 404);
        $validated = $request->validate([
            'fields' => ['nullable', 'array', 'max:40'],
            'fields.*.key' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fields.*.label' => ['required', 'string', 'max:150'],
            'fields.*.type' => ['required', Rule::in(['text', 'email', 'tel', 'number', 'url', 'date', 'textarea', 'select', 'radio', 'checkbox'])],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.help' => ['nullable', 'string', 'max:500'],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
        ]);

        $fields = collect($validated['fields'] ?? [])->map(function ($field) {
            $field['required'] = (bool) ($field['required'] ?? false);
            $field['options'] = in_array($field['type'], ['select', 'radio'], true)
                ? collect(preg_split('/\r\n|\r|\n/', $field['options'] ?? ''))->map(fn ($option) => trim($option))->filter()->values()->all()
                : [];

            return $field;
        })->values();
        if ($fields->pluck('key')->duplicates()->isNotEmpty()) {
            return back()->withInput()->withErrors(['fields' => 'Key field harus unik.']);
        }

        $form->update(['schema' => $fields->all()]);
        $audit->record('conference.form_updated', $form, $conference, newValues: ['version' => $form->version, 'field_count' => $fields->count()]);

        return back()->with('success', 'Draft form berhasil disimpan.');
    }

    public function publish(Request $request, Conference $conference, FormVersion $form, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $conference);
        abort_unless($form->conference_id === $conference->id && $form->status === 'draft', 404);

        DB::transaction(function () use ($conference, $form) {
            $conference->formVersions()->where('status', 'published')->update(['status' => 'archived']);
            $form->update(['status' => 'published', 'published_at' => now()]);
        });
        $audit->record('conference.form_published', $form, $conference, newValues: ['version' => $form->version]);

        return redirect()->route('conferences.show', $conference)->with('success', "Form versi {$form->version} dipublikasikan.");
    }

    private function draftFor(Conference $conference, Request $request): FormVersion
    {
        $draft = $conference->formVersions()->where('status', 'draft')->latest('version')->first();
        if ($draft) {
            return $draft;
        }

        $published = $conference->formVersions()->where('status', 'published')->latest('version')->first();

        return $conference->formVersions()->create([
            'version' => ($conference->formVersions()->max('version') ?? 0) + 1,
            'status' => 'draft',
            'schema' => $published?->schema ?? [],
            'created_by' => $request->user()->id,
        ]);
    }
}
