<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Models\EmailTemplate;
use App\Services\AuditLogger;
use App\Services\ConferenceMailer;
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
            'email_sender_name' => ['nullable', 'string', 'max:255'],
            'conference_default_cc' => ['nullable', 'string', 'max:2000'],
            'templates' => ['required', 'array'],
            'templates.*.subject' => ['required', 'string', 'max:500'],
            'templates.*.body' => ['required', 'string', 'max:20000'],
            'templates.*.default_cc' => ['nullable', 'string', 'max:2000'],
            'templates.*.is_enabled' => ['nullable', 'boolean'],
        ]);

        $settings = $conference->settings ?? [];
        $settings['default_cc'] = $this->emails($validated['conference_default_cc'] ?? '');
        $conference->update([
            'email_sender_name' => filled($validated['email_sender_name'] ?? null)
                ? trim($validated['email_sender_name'])
                : null,
            'settings' => $settings,
        ]);

        foreach ($conference->emailTemplates as $template) {
            $input = $validated['templates'][$template->id] ?? null;
            if (! $input) {
                continue;
            }
            $cc = $this->emails($input['default_cc'] ?? '');
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

    public function testSend(Request $request, Conference $conference, ConferenceMailer $mailer): RedirectResponse
    {
        $this->authorize('update', $conference);
        $validated = $request->validate([
            'test_template_id' => ['required', 'string'], 'test_recipients' => ['required', 'array'],
            'test_recipients.*' => ['nullable', 'email:rfc'],
            'templates' => ['required', 'array'], 'templates.*.subject' => ['required', 'string', 'max:500'],
            'templates.*.body' => ['required', 'string', 'max:20000'], 'templates.*.default_cc' => ['nullable', 'string', 'max:2000'],
        ]);
        $template = EmailTemplate::where('conference_id', $conference->id)->findOrFail($validated['test_template_id']);
        $recipient = $request->validate([
            "test_recipients.{$template->id}" => ['required', 'email:rfc'],
        ])['test_recipients'][$template->id];
        $input = $validated['templates'][$template->id] ?? abort(422);
        $mailer->queueTest($template, $recipient, $request->user(), $input['subject'], $input['body'], $this->emails($input['default_cc'] ?? ''));

        return back()->with('success', 'Test email dimasukkan ke antrean. Pantau statusnya di Monitoring Email.');
    }

    /** @return list<string> */
    private function emails(string $value): array
    {
        return collect(preg_split('/[,;\s]+/', $value))->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique()->values()->all();
    }
}
