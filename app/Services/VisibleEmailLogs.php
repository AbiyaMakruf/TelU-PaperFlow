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
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $adminConferenceIds = $user->conferenceMemberships()->where('is_active', true)
            ->where('role', ConferenceRole::Admin)->pluck('conference_id');

        return $query->where(fn ($scope) => $scope
            ->whereIn('conference_id', $adminConferenceIds)
            ->orWhere('sender_user_id', $user->id));
    }

    public function canAccess(User $user): bool
    {
        return $user->isSuperAdmin() || $user->conferenceMemberships()->where('is_active', true)
            ->where('role', ConferenceRole::Admin)->exists();
    }
}
