<?php

namespace App\Console\Commands;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Services\RevisionDeadlineReminderService;
use Illuminate\Console\Command;

class ClearStaleRevisionDeadlines extends Command
{
    protected $signature = 'paperflow:clear-stale-revision-deadlines {--dry-run : Report only without changing data}';

    protected $description = 'Clear revision deadlines left behind after an author revision upload.';

    public function handle(RevisionDeadlineReminderService $reminders): int
    {
        $submissions = Submission::query()->with('conference')
            ->whereNotNull('deadline_at')
            ->whereNotIn('status', [SubmissionStatus::NeedsAuthorCorrection, SubmissionStatus::WaitingAuthorRevision])
            ->where(fn ($query) => $query->where('revision_substatus', 'revised_by_author')->orWhereHas('files', fn ($files) => $files->where('source', 'author')))
            ->get();
        $this->table(['Paper', 'Status', 'Deadline'], $submissions->map(fn ($s) => [$s->paper_code, $s->status->label(), $s->formattedDeadline()])->all());
        if ($this->option('dry-run')) {
            $this->info("Dry run: {$submissions->count()} stale revision deadline(s) found.");

            return self::SUCCESS;
        }
        foreach ($submissions as $submission) {
            $submission->update(['deadline_at' => null]);
            $reminders->cancelForSubmission($submission, 'Cancelled during stale deadline cleanup after author revision upload.');
        }
        $this->info("Cleared {$submissions->count()} stale revision deadline(s).");

        return self::SUCCESS;
    }
}
