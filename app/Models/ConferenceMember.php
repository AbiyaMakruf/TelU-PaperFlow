<?php

namespace App\Models;

use App\Enums\ConferenceRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConferenceMember extends Model
{
    use HasFactory;

    protected $fillable = ['conference_id', 'user_id', 'role', 'is_active', 'added_by'];

    protected function casts(): array
    {
        return ['role' => ConferenceRole::class, 'is_active' => 'boolean'];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
