<?php

namespace App\Services;

use App\Jobs\SendLoggedEmail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ConferenceMailer
{
    /** @param array<string, string> $variables @param list<string> $cc */
    public function queue(Submission $submission, string $templateKey, array $variables, array $cc = [], ?User $sender = null, bool $replaceDefaultCc = false): ?EmailLog
    {
        $template = $submission->conference->emailTemplates()
            ->where('key', $templateKey)
            ->where('is_enabled', true)
            ->first();

        if (! $template) {
            return null;
        }

        $sender ??= Auth::user();
        $variables = [
            'conference' => $submission->conference->name,
            'paper_code' => $submission->paper_code,
            'author_name' => $submission->corresponding_author_name,
            'deadline' => $submission->deadline_at?->timezone($submission->conference->timezone)->format('F j, Y \a\t H:i T') ?? 'Please follow the deadline communicated by the committee.',
            'editor_name' => $sender?->name ?? 'Editorial Team',
            'editor_job_title' => $sender?->job_title ?? 'Publication Committee',
            'editor_affiliation' => $sender?->affiliation ?? $submission->conference->name,
            'editor_whatsapp' => $sender?->whatsapp() ?? '-',
            'editor_whatsapp_url' => $sender?->whatsapp() ? 'https://wa.me/'.PhoneNumber::whatsappDigits($sender->whatsapp()) : '-',
            ...$variables,
        ];
        $replace = collect($variables)->mapWithKeys(fn ($value, $key) => ['{{'.$key.'}}' => $value])->all();
        $subject = strtr($template->subject, $replace);
        $body = strtr($template->body, $replace);
        $coAuthorEmails = $submission->relationLoaded('authors')
            ? $submission->authors->reject(fn ($a) => mb_strtolower((string) $a->email) === mb_strtolower((string) $submission->corresponding_author_email))->pluck('email')->filter()->values()->all()
            : $submission->authors()->where('is_corresponding', false)->pluck('email')->filter()->values()->all();

        $defaults = [...$submission->conference->defaultCc(), ...($template->default_cc ?? []), ...$coAuthorEmails];
        $recipients = array_values(array_unique($replaceDefaultCc ? $cc : [...$defaults, ...$cc]));

        $log = EmailLog::create([
            'conference_id' => $submission->conference_id,
            'submission_id' => $submission->id,
            'template_key' => $templateKey,
            'recipient' => $submission->corresponding_author_email,
            'cc' => $recipients,
            'subject' => $subject,
            'sender_name' => $submission->conference->email_sender_name ?: $submission->conference->name,
            'sender_user_id' => $sender?->id,
            'body' => $body,
            'status' => 'queued',
        ]);
        SendLoggedEmail::dispatch($log, $body, $recipients);

        return $log;
    }

    /** @param list<string> $cc */
    public function queueTest(EmailTemplate $template, string $recipient, User $sender, string $subject, string $body, array $cc = []): EmailLog
    {
        $conference = $template->conference;
        $replace = [
            '{{conference}}' => $conference->name, '{{paper_code}}' => 'DEMO-001', '{{author_name}}' => 'Demo Author',
            '{{feedback}}' => "• Example revision item\n• Please verify IEEE formatting", '{{portal_url}}' => url('/submission/access/demo-token'),
            '{{deadline}}' => now($conference->timezone)->addDays(7)->format('F j, Y \a\t H:i T'), '{{editor_name}}' => $sender->name,
            '{{editor_job_title}}' => $sender->job_title ?: 'Publication Committee', '{{editor_affiliation}}' => $sender->affiliation ?: $conference->name,
            '{{editor_whatsapp}}' => $sender->whatsapp() ?: '-', '{{editor_whatsapp_url}}' => $sender->whatsapp() ? 'https://wa.me/'.PhoneNumber::whatsappDigits($sender->whatsapp()) : '-',
        ];
        $renderedSubject = strtr($subject, $replace);
        $renderedBody = strtr($body, $replace);
        $log = EmailLog::create([
            'conference_id' => $conference->id, 'template_key' => 'test:'.$template->key, 'recipient' => $recipient,
            'cc' => $cc, 'subject' => $renderedSubject, 'body' => $renderedBody, 'sender_user_id' => $sender->id,
            'sender_name' => $conference->email_sender_name ?: $conference->name, 'status' => 'queued',
        ]);
        SendLoggedEmail::dispatch($log, $renderedBody, $cc);

        return $log;
    }

    public function resend(EmailLog $original, User $sender): EmailLog
    {
        $copy = EmailLog::create([
            'conference_id' => $original->conference_id, 'submission_id' => $original->submission_id,
            'template_key' => $original->template_key, 'recipient' => $original->recipient, 'cc' => $original->cc ?? [],
            'subject' => $original->subject, 'body' => $original->body, 'sender_user_id' => $sender->id,
            'sender_name' => $original->sender_name, 'status' => 'queued',
        ]);
        SendLoggedEmail::dispatch($copy, (string) $copy->body, $copy->cc ?? []);

        return $copy;
    }
}
