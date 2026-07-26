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
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {}

    public function handle(): void
    {
        $this->emailLog->update(['status' => 'sending', 'attempts' => $this->attempts()]);

        try {
            $actionUrl = $this->actionUrl;
            if ($actionUrl === null) {
                if (preg_match('/https?:\/\/[^\s<">]+/', $this->body, $matches)) {
                    $actionUrl = rtrim($matches[0], '.,);');
                }
            }

            $cleanKey = str_replace('test:', '', $this->emailLog->template_key);
            $actionLabel = $this->actionLabel ?? match ($cleanKey) {
                'revision_requested' => 'Open Portal & Upload Revision',
                'submission_received' => 'Track Submission',
                'paper_completed' => 'Open Author Portal',
                'deadline_reminder' => 'Open Author Portal',
                'new_submission_admin' => 'Open Paper & Assign PIC',
                'assigned_editor' => 'Open Paper & Complete Checklist',
                'assigned_reviewer' => 'Open Paper & Review',
                'author_revision_uploaded' => 'Inspect Updated Paper',
                default => 'Track Submission',
            };
            $mail = new PaperflowMail(
                mailSubject: $this->emailLog->subject,
                messageBody: $this->body,
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
