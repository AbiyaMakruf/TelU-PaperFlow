<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class VisibleSubmissions
{
    /** @return Builder<Submission> */
    public function for(User $user): Builder
    {
        $query = Submission::query();
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $activeConferenceIds = $user->conferenceMemberships()
            ->where('is_active', true)
            ->pluck('conference_id');

        return $query->whereIn('conference_id', $activeConferenceIds);
    }
}
