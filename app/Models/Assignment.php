<?php

namespace App\Models;

use App\Enums\ConferenceRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['submission_id', 'user_id', 'role', 'assigned_by', 'note', 'reassignment_reason', 'assigned_at', 'unassigned_at'];

    protected function casts(): array
    {
        return ['role' => ConferenceRole::class, 'assigned_at' => 'datetime', 'unassigned_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
