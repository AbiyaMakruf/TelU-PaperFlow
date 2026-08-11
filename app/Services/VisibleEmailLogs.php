<?php

namespace App\Services;

use App\Enums\ConferenceRole;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class VisibleEmailLogs
{
    /** @return Builder<EmailLog> */
    public function for(User $user): Builder
    {
        $query = EmailLog::query();
        $activeWorkspaceId = session('active_conference_id');

        if ($user->isSuperAdmin()) {
            if ($activeWorkspaceId) {
                return $query->where('conference_id', $activeWorkspaceId);
            }

            return $query;
        }

        $adminConferenceIds = $user->conferenceMemberships()
            ->where('is_active', true)
            ->whereIn('role', [ConferenceRole::Admin, 'admin', 'conference_admin'])
            ->pluck('conference_id');

        if ($activeWorkspaceId && $adminConferenceIds->contains($activeWorkspaceId)) {
            return $query->where('conference_id', $activeWorkspaceId);
        }

        return $query->whereIn('conference_id', $adminConferenceIds);
    }

    public function canAccess(User $user): bool
    {
        $activeWorkspaceId = session('active_conference_id');

        if ($user->isSuperAdmin()) {
            return true;
        }

        $adminConferenceIds = $user->conferenceMemberships()
            ->where('is_active', true)
            ->whereIn('role', [ConferenceRole::Admin, 'admin', 'conference_admin'])
            ->pluck('conference_id');

        if ($activeWorkspaceId) {
            return $adminConferenceIds->contains($activeWorkspaceId);
        }

        return $adminConferenceIds->isNotEmpty();
    }
}
