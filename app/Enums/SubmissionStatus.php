<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Submitted = 'submitted';
    case NeedsAuthorCorrection = 'needs_author_correction';
    case ReadyForAssignment = 'ready_for_assignment';
    case EditorialReview = 'editorial_review';
    case WaitingAuthorRevision = 'waiting_author_revision';
    case ReviewerReview = 'reviewer_review';
    case ReviewerChangesRequested = 'reviewer_changes_requested';
    case ReadyForEdas = 'ready_for_edas';
    case EdasFixRequired = 'edas_fix_required';
    case Done = 'done';
    case Withdrawn = 'withdrawn';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submission Received',
            self::NeedsAuthorCorrection => 'Author Correction Required',
            self::ReadyForAssignment => 'Waiting for Editor Assignment',
            self::EditorialReview => 'Editorial Review in Progress',
            self::WaitingAuthorRevision => 'Waiting for Author Revision',
            self::ReviewerReview => 'Pre-EDAS Technical Review',
            self::ReviewerChangesRequested => 'Reviewer Revision Requested',
            self::ReadyForEdas => 'Ready for EDAS Upload',
            self::EdasFixRequired => 'EDAS Correction Required',
            self::Done => 'Completed / Done',
            self::Withdrawn => 'Withdrawn',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::NeedsAuthorCorrection => 'warning',
            self::ReadyForAssignment => 'indigo',
            self::EditorialReview => 'blue',
            self::WaitingAuthorRevision => 'amber',
            self::ReviewerReview => 'purple',
            self::ReviewerChangesRequested => 'orange',
            self::ReadyForEdas => 'teal',
            self::EdasFixRequired => 'rose',
            self::Done => 'success',
            self::Withdrawn => 'slate',
            self::Rejected => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Done, self::Rejected, self::Withdrawn], true);
    }
}
