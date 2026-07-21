<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Conference;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function record(
        string $event,
        ?Model $auditable = null,
        ?Conference $conference = null,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'conference_id' => $conference?->id,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
