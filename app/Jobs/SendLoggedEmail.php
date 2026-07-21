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
            $actionLabel = match ($this->emailLog->template_key) {
                'revision_requested' => 'Buka portal & unggah revisi',
                'submission_received' => 'Pantau submission',
                'paper_completed' => 'Buka portal paper',
                default => 'Buka Paperflow',
            };
            $mail = new PaperflowMail(
                mailSubject: $this->emailLog->subject,
                messageBody: $this->body,
                senderName: $this->emailLog->sender_name ?: (string) config('mail.from.name'),
                contextName: $this->emailLog->conference?->name ?: 'Paperflow',
                actionUrl: $actionUrl,
                actionLabel: $actionLabel,
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
