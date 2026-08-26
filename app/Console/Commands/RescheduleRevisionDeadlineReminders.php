<?php

namespace App\Console\Commands;

use App\Enums\SubmissionStatus;
use App\Models\ScheduledRevisionReminder;
use App\Models\Submission;
use App\Services\RevisionDeadlineReminderService;
use Illuminate\Console\Command;

class RescheduleRevisionDeadlineReminders extends Command
{
    protected $signature = 'paperflow:reschedule-revision-deadline-reminders';

    protected $description = 'Reschedule pending revision reminders for 08:00 WIB.';

    public function handle(RevisionDeadlineReminderService $reminders): int
    {
        $cancelled = ScheduledRevisionReminder::where('status', 'scheduled')->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'reason' => 'Rescheduled for 08:00 WIB after timezone correction.',
        ]);
        $submissions = Submission::query()->with(['conference', 'editor'])
            ->whereIn('status', [SubmissionStatus::NeedsAuthorCorrection, SubmissionStatus::WaitingAuthorRevision])
            ->whereNotNull('deadline_at')->get();
        foreach ($submissions as $submission) {
            $reminders->schedule($submission);
        }
        $this->info("Cancelled {$cancelled} pending reminder(s) and rescheduled {$submissions->count()} eligible paper(s) for 08:00 WIB.");

        return self::SUCCESS;
    }
}
