<?php

namespace App\Enums;

enum ConferenceStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Closed => 'Closed',
            self::Archived => 'Archived',
        };
    }
}
