<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadAttempt extends Model
{
    use HasUlids;

    protected $fillable = ['submission_id', 'user_id', 'source', 'label', 'original_name', 'mime_type', 'size', 'temporary_path', 'notes', 'is_final', 'status', 'error', 'attempts', 'retried_at'];

    protected function casts(): array
    {
        return ['is_final' => 'boolean', 'retried_at' => 'datetime'];
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
