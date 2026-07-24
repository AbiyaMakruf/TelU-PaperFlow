<?php

namespace App\Console\Commands;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Notifications\WorkflowNotification;
use App\Services\ConferenceMailer;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'paperflow:send-reminders';

    protected $description = 'Send automated reminders for overdue papers, unassigned submissions, pending author revisions, and pending EDAS steps.';

    public function handle(ConferenceMailer $mailer): int
    {
        $remindedCount = 0;

        // 1. Remind authors with pending revisions where deadline is approaching or past
        $pendingAuthorSubmissions = Submission::query()
            ->whereIn('status', [SubmissionStatus::NeedsAuthorCorrection, SubmissionStatus::WaitingAuthorRevision])
            ->get();

        foreach ($pendingAuthorSubmissions as $sub) {
            $token = $sub->getSafeAuthorToken();
            if ($token) {
                $portalUrl = route('author.portal', $token);
                $mailer->queue($sub->load('conference'), 'deadline_reminder', ['portal_url' => $portalUrl]);
                $remindedCount++;
            }
        }

        // 2. Notify assigned editors/reviewers for overdue papers
        $overdueSubmissions = Submission::query()
            ->where('deadline_at', '<', now())
            ->whereNotIn('status', [SubmissionStatus::Done, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn])
            ->get();

        foreach ($overdueSubmissions as $sub) {
            if ($sub->editor) {
                $sub->editor->notify(new WorkflowNotification($sub, 'Overdue Paper Deadline Reminder', "Paper {$sub->paper_code} has exceeded its set deadline."));
                $remindedCount++;
            }
            if ($sub->reviewer) {
                $sub->reviewer->notify(new WorkflowNotification($sub, 'Overdue Paper Deadline Reminder', "Paper {$sub->paper_code} has exceeded its set deadline."));
                $remindedCount++;
            }
        }

        $this->info("Automated reminders process completed. Total notifications/emails sent: {$remindedCount}.");

        return Command::SUCCESS;
    }
}
