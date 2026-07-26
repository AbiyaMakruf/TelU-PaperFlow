<?php

use App\Enums\SubmissionStatus;
use App\Models\ReviewCycle;
use App\Models\Submission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $nonActiveStatuses = [
            SubmissionStatus::WaitingAuthorRevision->value,
            SubmissionStatus::NeedsAuthorCorrection->value,
            SubmissionStatus::ReviewerChangesRequested->value,
            SubmissionStatus::ReadyForEdas->value,
            SubmissionStatus::EdasFixRequired->value,
            SubmissionStatus::Done->value,
            SubmissionStatus::Rejected->value,
            SubmissionStatus::Withdrawn->value,
        ];

        $submissionIds = Submission::whereIn('status', $nonActiveStatuses)->pluck('id');

        ReviewCycle::whereIn('submission_id', $submissionIds)
            ->where('status', 'open')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};
