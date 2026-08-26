<?php

namespace App\Jobs;

use App\Mail\PaperflowMail;
use App\Models\EmailLog;
use App\Models\ScheduledRevisionReminder;
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
                'send_reviewer', 'reviewer_changes', 'reviewer_approve', 'edas_fix', 'revert_done' => 'Open Paper in Paperflow',
                default => 'Track Submission',
            };
            $body = $this->body;
            $accentColor = $this->emailLog->conference?->brandAccent() ?? '#f47c20';

            if ($actionUrl) {
                if (str_contains($body, '<a href=')) {
                    $actionUrl = null;
                } else {
                    $buttonHtml = '<div style="margin:16px 0;text-align:center;"><a href="'.e($actionUrl).'" style="display:inline-block;background:'.$accentColor.';color:#ffffff;text-decoration:none;font-size:13.5px;font-weight:800;padding:12px 26px;border-radius:8px;box-shadow:0 3px 10px rgba(244,124,32,0.25);">'.e($actionLabel).'</a></div>';

                    $markdownPattern = '/(?:\r?\n)*\s*\[[^\]]*\]\(\s*'.preg_quote($actionUrl, '/').'\s*\)\s*(?:\r?\n)*/i';
                    $rawUrlPattern = '/(?:\r?\n)*\s*'.preg_quote($actionUrl, '/').'\s*(?:\r?\n)*/i';

                    if (preg_match($markdownPattern, $body)) {
                        $body = preg_replace($markdownPattern, "\n".$buttonHtml."\n", $body);
                    } elseif (preg_match($rawUrlPattern, $body)) {
                        $body = preg_replace($rawUrlPattern, "\n".$buttonHtml."\n", $body);
                    }

                    $actionUrl = null;
                }
            }

            $mail = new PaperflowMail(
                mailSubject: $this->emailLog->subject,
                messageBody: $body,
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
            ScheduledRevisionReminder::where('email_log_id', $this->emailLog->id)->where('status', 'queued')->update(['status' => 'sent', 'sent_at' => now(), 'reason' => 'Email delivered successfully.']);
        } catch (Throwable $exception) {
            $this->emailLog->update([
                'status' => 'failed',
                'attempts' => $this->attempts(),
                'error' => mb_substr($exception->getMessage(), 0, 5000),
            ]);
            ScheduledRevisionReminder::where('email_log_id', $this->emailLog->id)->where('status', 'queued')->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 5000), 'reason' => 'Email delivery failed.']);

            throw $exception;
        }
    }
}
