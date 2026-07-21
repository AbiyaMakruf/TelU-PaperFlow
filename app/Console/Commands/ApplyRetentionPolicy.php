<?php

namespace App\Console\Commands;

use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\FileVersion;
use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ApplyRetentionPolicy extends Command
{
    protected $signature = 'paperflow:apply-retention-policy {--days= : Override retention period in days}';

    protected $description = 'Applies retention policy to purge or soft-delete files from completed or terminal submissions.';

    public function handle(): int
    {
        $conferences = Conference::all();
        $totalCleaned = 0;

        foreach ($conferences as $conference) {
            $days = $this->option('days') ?: ($conference->settings['retention_days'] ?? null);
            if (! $days) {
                continue;
            }

            $daysInt = (int) $days;
            $cutoff = now()->subDays($daysInt);

            $submissions = Submission::query()
                ->where('conference_id', $conference->id)
                ->whereIn('status', [SubmissionStatus::Done, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn])
                ->where('updated_at', '<', $cutoff)
                ->with('files')
                ->get();

            foreach ($submissions as $sub) {
                foreach ($sub->files as $file) {
                    if ($file->disk === 'local') {
                        Storage::disk('local')->delete($file->storage_path);
                    }
                    $file->delete();
                    $totalCleaned++;
                }
            }
        }

        $this->info("Retention policy applied. Purged {$totalCleaned} file versions.");

        return Command::SUCCESS;
    }
}
