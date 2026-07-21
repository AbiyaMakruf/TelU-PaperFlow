<?php

namespace App\Enums;

enum ReviewStage: string
{
    case Editorial = 'editorial';
    case Reviewer = 'reviewer';

    public function label(): string
    {
        return $this === self::Editorial ? 'Editorial' : 'Reviewer';
    }
}
