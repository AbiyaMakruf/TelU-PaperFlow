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
        'conference_id', 'form_version_id', 'paper_id', 'paper_code', 'manuscript_format', 'title', 'corresponding_author_name',
        'corresponding_author_email', 'corresponding_author_phone', 'answers', 'status', 'is_flagged_duplicate', 'duplicate_notes',
        'editor_id', 'reviewer_id', 'author_token_hash', 'author_token_encrypted', 'author_token_expires_at',
        'submitted_at', 'validated_at', 'completed_at', 'edas_reference', 'edas_notes', 'lock_version',
        'deadline_at', 'edas_submitted_at', 'edas_submitted_by', 'edas_approved_at', 'edas_approved_by',
    ];

    protected $hidden = ['author_token_hash', 'author_token_encrypted'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'status' => SubmissionStatus::class,
            'is_flagged_duplicate' => 'boolean',
            'author_token_encrypted' => 'encrypted',
            'author_token_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'completed_at' => 'datetime',
            'deadline_at' => 'datetime', 'edas_submitted_at' => 'datetime', 'edas_approved_at' => 'datetime',
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

    public function uploadAttempts(): HasMany
    {
        return $this->hasMany(UploadAttempt::class)->latest();
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class)->latest();
    }

    public function edasSubmitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edas_submitted_by');
    }

    public function edasApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edas_approved_by');
    }

    public function isOverdue(): bool
    {
        return $this->deadline_at?->isPast() && ! in_array($this->status, [SubmissionStatus::Done, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn], true);
    }
}
