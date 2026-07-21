<?php

namespace App\Services;

use App\Enums\ConferenceRole;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\StatusHistory;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use DomainException;
use Illuminate\Support\Facades\DB;

class SubmissionWorkflow
{
    /** @var array<string, list<SubmissionStatus>> */
    private const TRANSITIONS = [
        'submitted' => [SubmissionStatus::NeedsAuthorCorrection, SubmissionStatus::ReadyForAssignment, SubmissionStatus::Rejected],
        'needs_author_correction' => [SubmissionStatus::Submitted, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn],
        'ready_for_assignment' => [SubmissionStatus::EditorialReview, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn],
        'editorial_review' => [SubmissionStatus::WaitingAuthorRevision, SubmissionStatus::ReviewerReview, SubmissionStatus::Withdrawn, SubmissionStatus::Rejected],
        'waiting_author_revision' => [SubmissionStatus::EditorialReview, SubmissionStatus::Withdrawn],
        'reviewer_review' => [SubmissionStatus::ReviewerChangesRequested, SubmissionStatus::ReadyForEdas, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn],
        'reviewer_changes_requested' => [SubmissionStatus::EditorialReview, SubmissionStatus::ReviewerReview],
        'ready_for_edas' => [SubmissionStatus::EdasFixRequired, SubmissionStatus::Done, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn],
        'edas_fix_required' => [SubmissionStatus::EditorialReview, SubmissionStatus::ReviewerReview, SubmissionStatus::ReadyForEdas],
    ];

    public function canTransition(SubmissionStatus $from, SubmissionStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function transition(
        Submission $submission,
        SubmissionStatus $to,
        ?User $actor = null,
        ?string $note = null,
        array $metadata = [],
    ): Submission {
        return DB::transaction(function () use ($submission, $to, $actor, $note, $metadata) {
            /** @var Submission $locked */
            $locked = Submission::query()->lockForUpdate()->findOrFail($submission->id);
            $from = $locked->status;

            if (! $this->canTransition($from, $to)) {
                throw new DomainException("Perubahan status dari {$from->value} ke {$to->value} tidak diizinkan.");
            }

            $changes = ['status' => $to, 'lock_version' => $locked->lock_version + 1];
            if ($to === SubmissionStatus::ReadyForAssignment) {
                $changes['validated_at'] = now();
            }
            if ($to === SubmissionStatus::Done) {
                $changes['completed_at'] = now();
            }

            $locked->update($changes);
            StatusHistory::create([
                'submission_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor?->id,
                'note' => $note,
                'metadata' => $metadata ?: null,
                'created_at' => now(),
            ]);

            $updated = $locked->refresh();
            collect([$updated->editor, $updated->reviewer])->filter()->unique('id')->each(fn ($user) => $user->notify(new WorkflowNotification($updated, 'Status paper berubah', "{$updated->paper_code} sekarang berstatus {$to->label()}.")));
            app(AuditLogger::class)->record('submission.status_changed', $updated, $updated->conference, ['status' => $from->value], ['status' => $to->value, 'note' => $note]);

            return $updated;
        });
    }

    public function assign(
        Submission $submission,
        User $assignee,
        ConferenceRole $role,
        User $actor,
        ?string $note = null,
    ): Submission {
        if (! in_array($role, [ConferenceRole::Editorial, ConferenceRole::Reviewer], true)) {
            throw new DomainException('Role assignment harus editorial atau reviewer.');
        }

        if (! $assignee->hasConferenceRole($submission->conference_id, $role)) {
            throw new DomainException('Pengguna tidak memiliki role aktif pada conference ini.');
        }

        return DB::transaction(function () use ($submission, $assignee, $role, $actor, $note) {
            Assignment::query()
                ->where('submission_id', $submission->id)
                ->where('role', $role->value)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            Assignment::create([
                'submission_id' => $submission->id,
                'user_id' => $assignee->id,
                'role' => $role,
                'assigned_by' => $actor->id,
                'note' => $note,
                'assigned_at' => now(),
            ]);

            $submission->update([
                $role === ConferenceRole::Editorial ? 'editor_id' : 'reviewer_id' => $assignee->id,
            ]);
            $assignee->notify(new WorkflowNotification($submission, 'Assignment baru', "Anda ditugaskan pada {$submission->paper_code} sebagai {$role->label()}."));
            app(AuditLogger::class)->record('submission.assigned', $submission, $submission->conference, [], ['user_id' => $assignee->id, 'role' => $role->value]);

            if ($role === ConferenceRole::Editorial && $submission->status === SubmissionStatus::ReadyForAssignment) {
                return $this->transition($submission, SubmissionStatus::EditorialReview, $actor, $note);
            }

            return $submission->refresh();
        });
    }

    /** @return list<SubmissionStatus> */
    public function availableTransitions(Submission $submission): array
    {
        return self::TRANSITIONS[$submission->status->value] ?? [];
    }
}
