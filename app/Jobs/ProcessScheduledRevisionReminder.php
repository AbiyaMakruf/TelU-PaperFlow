<?php

namespace App\Jobs;

use App\Models\ScheduledRevisionReminder;
use App\Services\RevisionDeadlineReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessScheduledRevisionReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $reminderId) {}

    public function handle(RevisionDeadlineReminderService $service): void
    {
        $reminder = ScheduledRevisionReminder::find($this->reminderId);
        if ($reminder) {
            $service->process($reminder);
        }
    }
}
