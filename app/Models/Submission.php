<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'conference_id', 'form_version_id', 'paper_code', 'title', 'corresponding_author_name',
        'corresponding_author_email', 'corresponding_author_phone', 'answers', 'status',
        'editor_id', 'reviewer_id', 'author_token_hash', 'author_token_expires_at',
        'submitted_at', 'validated_at', 'completed_at', 'edas_reference', 'edas_notes', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'status' => SubmissionStatus::class,
            'author_token_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function authors(): HasMany
    {
        return $this->hasMany(SubmissionAuthor::class)->orderBy('sort_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->latest('assigned_at');
    }

    public function files(): HasMany
    {
        return $this->hasMany(FileVersion::class)->orderByDesc('version_number');
    }

    public function reviewCycles(): HasMany
    {
        return $this->hasMany(ReviewCycle::class)->latest('cycle_number');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class)->latest();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(StatusHistory::class)->latest('created_at');
    }
}
