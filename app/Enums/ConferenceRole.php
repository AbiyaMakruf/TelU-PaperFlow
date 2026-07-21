<?php

namespace App\Enums;

enum ConferenceRole: string
{
    case Admin = 'conference_admin';
    case Editorial = 'editorial';
    case Reviewer = 'reviewer';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Conference Admin',
            self::Editorial => 'Editorial Team',
            self::Reviewer => 'Reviewer',
            self::Viewer => 'Viewer',
        };
    }
}
