<?php

namespace App\Services;

use App\Enums\ConferenceRole;
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

        $memberships = $user->conferenceMemberships()
            ->where('is_active', true)
            ->get(['conference_id', 'role']);
        $activeConferenceIds = $memberships->pluck('conference_id');
        $oversightIds = $memberships
            ->whereIn('role', [ConferenceRole::Admin, ConferenceRole::Viewer])
            ->pluck('conference_id');

        return $query->where(fn ($scope) => $scope
            ->whereIn('conference_id', $oversightIds)
            ->orWhere(fn ($assigned) => $assigned
                ->whereIn('conference_id', $activeConferenceIds)
                ->where(fn ($assignee) => $assignee
                    ->where('editor_id', $user->id)
                    ->orWhere('reviewer_id', $user->id))));
    }
}
