<?php

namespace App\Policies;

use App\Enums\ConferenceRole;
use App\Models\Conference;
use App\Models\User;

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
        return $user->hasConferenceRole($conference, ConferenceRole::Admin);
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
