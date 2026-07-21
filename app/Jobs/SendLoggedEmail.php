<?php

namespace App\Jobs;

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
            Mail::raw($this->body, function ($message) {
                $message->to($this->emailLog->recipient)->subject($this->emailLog->subject);
                if ($this->cc !== []) {
                    $message->cc($this->cc);
                }
            });
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
