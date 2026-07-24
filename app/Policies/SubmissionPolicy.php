<?php

namespace App\Policies;

use App\Enums\ConferenceRole;
use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Submission $submission): bool
    {
        return $user->isSuperAdmin()
            || $user->conferenceMemberships()
                ->where('conference_id', $submission->conference_id)
                ->where('is_active', true)
                ->exists();
    }

    public function assign(User $user, Submission $submission): bool
    {
        return $user->hasConferenceRole($submission->conference_id, ConferenceRole::Admin);
    }

    public function revertCompleted(User $user, Submission $submission): bool
    {
        return $user->isSuperAdmin()
            || $user->hasConferenceRole($submission->conference_id, ConferenceRole::Admin);
    }

    public function editorialReview(User $user, Submission $submission): bool
    {
        return $user->isSuperAdmin()
            || $submission->editor_id === $user->id
            || $user->hasConferenceRole($submission->conference_id, ConferenceRole::Admin);
    }

    public function reviewerReview(User $user, Submission $submission): bool
    {
        return $user->isSuperAdmin()
            || $submission->reviewer_id === $user->id
            || $user->hasConferenceRole($submission->conference_id, ConferenceRole::Admin);
    }
}
