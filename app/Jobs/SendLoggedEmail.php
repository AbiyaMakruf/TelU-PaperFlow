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
                $cleanBody = preg_replace('/\s*https?:\/\/[^\s]+\s*/', "\n\n", $cleanBody);
                $cleanBody = preg_replace("/\n{3,}/", "\n\n", trim((string) $cleanBody));
            }

            $actionLabel = match ($this->emailLog->template_key) {
                'revision_requested' => 'Upload Revision',
                'submission_received' => 'Track Submission',
                'paper_completed' => 'View Paper Portal',
                default => 'Open Paperflow',
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
