<?php

namespace App\Jobs;

use App\Mail\PaperflowMail;
use App\Models\EmailLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendLoggedEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param list<string> $cc */
    public function __construct(
        public EmailLog $emailLog,
        public string $body,
        public array $cc = [],
    ) {}

    public function handle(): void
    {
        $this->emailLog->update(['status' => 'sending', 'attempts' => $this->attempts()]);

        try {
            preg_match('/https?:\/\/[^\s]+/', $this->body, $matches);
            $actionUrl = isset($matches[0]) ? rtrim($matches[0], '.,);') : null;
            $cleanBody = $this->body;
            if ($actionUrl !== null) {
                $cleanBody = str_replace($actionUrl, '', $cleanBody);
                $cleanBody = preg_replace('/(Please submit your revision or update your details through your private portal:\s*|You can track the progress of your paper and manage your submission via your private author portal:\s*|Please visit your author portal to review the requirements:\s*|Please log in to Paperflow to inspect the manuscript and complete the IEEE compliance checklist:\s*|Please log in to Paperflow to inspect the updated manuscript files and checklist:\s*|Please log in to Paperflow to inspect the manuscript, validate the submission, and assign the Editorial and Reviewer PICs:\s*|Please log in to Paperflow to record the EDAS manuscript upload:\s*)/i', '', $cleanBody);
                $cleanBody = preg_replace("/\n{3,}/", "\n\n", trim((string) $cleanBody));
            }

            $cleanKey = str_replace('test:', '', $this->emailLog->template_key);
            $actionLabel = match ($cleanKey) {
                'revision_requested' => 'Open Portal & Upload Revision',
                'submission_received' => 'Open Author Portal',
                'paper_completed' => 'Open Author Portal',
                'deadline_reminder' => 'Open Author Portal',
                'new_submission_admin' => 'Open Paper & Assign PIC',
                'assigned_editor' => 'Open Paper & Complete Checklist',
                'assigned_reviewer' => 'Open Paper & Review',
                'author_revision_uploaded' => 'Inspect Updated Paper',
                default => 'Open Portal & Upload Revision',
            };
            $mail = new PaperflowMail(
                mailSubject: $this->emailLog->subject,
                messageBody: $cleanBody,
                senderName: $this->emailLog->sender_name ?: (string) config('mail.from.name'),
                contextName: $this->emailLog->conference?->name ?: 'Paperflow',
                actionUrl: $actionUrl,
                actionLabel: $actionLabel,
                primaryColor: $this->emailLog->conference?->brandPrimary() ?? '#102a43',
                accentColor: $this->emailLog->conference?->brandAccent() ?? '#f47c20',
                logoUrl: $this->emailLog->conference?->brandLogoUrl(),
            );
            $pending = Mail::to($this->emailLog->recipient);
            if ($this->cc !== []) {
                $pending->cc($this->cc);
            }
            $pending->send($mail);
            $this->emailLog->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
        } catch (Throwable $exception) {
            $this->emailLog->update([
                'status' => 'failed',
                'attempts' => $this->attempts(),
                'error' => mb_substr($exception->getMessage(), 0, 5000),
            ]);

            throw $exception;
        }
    }
}
