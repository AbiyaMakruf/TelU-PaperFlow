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
            self::Submitted => 'Submission baru',
            self::NeedsAuthorCorrection => 'Perlu koreksi author',
            self::ReadyForAssignment => 'Siap di-assign',
            self::EditorialReview => 'Pemeriksaan editorial',
            self::WaitingAuthorRevision => 'Menunggu revisi author',
            self::ReviewerReview => 'Pemeriksaan reviewer',
            self::ReviewerChangesRequested => 'Dikembalikan reviewer',
            self::ReadyForEdas => 'Ready for EDAS',
            self::EdasFixRequired => 'Perbaikan EDAS',
            self::Done => 'Selesai',
            self::Withdrawn => 'Ditarik',
            self::Rejected => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Done => 'success',
            self::Rejected, self::Withdrawn => 'danger',
            self::NeedsAuthorCorrection, self::WaitingAuthorRevision => 'warning',
            self::ReadyForEdas => 'info',
            default => 'primary',
        };
    }
}
