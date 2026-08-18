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
        $query = Submission::query()->whereHas('conference');

        $activeWorkspaceId = session('active_conference_id');

        if ($user->isSuperAdmin()) {
            if ($activeWorkspaceId) {
                return $query->where('conference_id', $activeWorkspaceId);
            }

            return $query;
        }

        $activeConferenceIds = $user->conferenceMemberships()
            ->where('is_active', true)
            ->pluck('conference_id');

        if ($activeWorkspaceId && $activeConferenceIds->contains($activeWorkspaceId)) {
            return $query->where('conference_id', $activeWorkspaceId);
        }

        return $query->whereIn('conference_id', $activeConferenceIds);
    }
}
