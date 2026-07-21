<?php

namespace App\Services;

use App\Jobs\SendLoggedEmail;
use App\Models\EmailLog;
use App\Models\Submission;

class ConferenceMailer
{
    /** @param array<string, string> $variables @param list<string> $cc */
    public function queue(Submission $submission, string $templateKey, array $variables, array $cc = []): ?EmailLog
    {
        $template = $submission->conference->emailTemplates()
            ->where('key', $templateKey)
            ->where('is_enabled', true)
            ->first();

        if (! $template) {
            return null;
        }

        $variables = [
            'conference' => $submission->conference->name,
            'paper_code' => $submission->paper_code,
            'author_name' => $submission->corresponding_author_name,
            ...$variables,
        ];
        $replace = collect($variables)->mapWithKeys(fn ($value, $key) => ['{{'.$key.'}}' => $value])->all();
        $subject = strtr($template->subject, $replace);
        $body = strtr($template->body, $replace);
        $recipients = array_values(array_unique([...($template->default_cc ?? []), ...$cc]));

        $log = EmailLog::create([
            'conference_id' => $submission->conference_id,
            'submission_id' => $submission->id,
            'template_key' => $templateKey,
            'recipient' => $submission->corresponding_author_email,
            'cc' => $recipients,
            'subject' => $subject,
            'sender_name' => $submission->conference->email_sender_name ?: $submission->conference->name,
            'status' => 'queued',
        ]);
        SendLoggedEmail::dispatch($log, $body, $recipients);

        return $log;
    }
}
