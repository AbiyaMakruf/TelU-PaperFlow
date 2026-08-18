<?php

namespace App\Policies;

use App\Enums\ConferenceRole;
use App\Models\Conference;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ConferencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Conference $conference): bool
    {
        return $user->isSuperAdmin() || $user->conferenceMemberships()
            ->where('conference_id', $conference->id)
            ->where('is_active', true)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Conference $conference): bool
    {
        $allowed = $user->hasConferenceRole($conference, ConferenceRole::Admin);
        if (! $allowed) {
            Log::warning('[Paperflow Authorization] 403 Forbidden on Conference Update/EDAS', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
                'is_active' => $user->is_active,
                'conference_id' => $conference->id,
                'conference_name' => $conference->name,
                'memberships' => $user->conferenceMemberships()->get(['conference_id', 'role', 'is_active'])->toArray(),
            ]);
        }

        return $allowed;
    }

    public function delete(User $user, Conference $conference): bool
    {
        return $user->isSuperAdmin();
    }

    public function manageMembers(User $user, Conference $conference): bool
    {
        return $user->hasConferenceRole($conference, ConferenceRole::Admin);
    }
}
