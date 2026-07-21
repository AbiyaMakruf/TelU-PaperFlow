<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function edit(Conference $conference): View
    {
        $this->authorize('update', $conference);
        $templates = $conference->emailTemplates()->orderBy('key')->get();

        return view('conferences.email-templates', compact('conference', 'templates'));
    }

    public function update(Request $request, Conference $conference, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $conference);
        $validated = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*.subject' => ['required', 'string', 'max:500'],
            'templates.*.body' => ['required', 'string', 'max:20000'],
            'templates.*.default_cc' => ['nullable', 'string', 'max:2000'],
            'templates.*.is_enabled' => ['nullable', 'boolean'],
        ]);

        foreach ($conference->emailTemplates as $template) {
            $input = $validated['templates'][$template->id] ?? null;
            if (! $input) {
                continue;
            }
            $cc = collect(preg_split('/[,;\s]+/', $input['default_cc'] ?? ''))
                ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->values()->all();
            $template->update([
                'subject' => $input['subject'],
                'body' => $input['body'],
                'default_cc' => $cc,
                'is_enabled' => (bool) ($input['is_enabled'] ?? false),
            ]);
        }
        $audit->record('conference.email_templates_updated', $conference, $conference);

        return back()->with('success', 'Template email berhasil diperbarui.');
    }
}
