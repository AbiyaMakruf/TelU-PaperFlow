<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Services\PhoneNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Submission extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'conference_id', 'form_version_id', 'paper_id', 'paper_code', 'original_paper_code', 'manuscript_format', 'initial_page_count', 'final_page_count', 'title', 'original_title', 'corresponding_author_name',
        'corresponding_author_email', 'original_author_email', 'corresponding_author_phone', 'answers', 'status', 'submission_source', 'is_flagged_duplicate', 'duplicate_notes',
        'editor_id', 'reviewer_id', 'author_token_hash', 'author_token_encrypted', 'author_token_expires_at',
        'submitted_at', 'validated_at', 'completed_at', 'edas_reference', 'edas_notes', 'lock_version',
        'deadline_at', 'edas_submitted_at', 'edas_submitted_by', 'edas_approved_at', 'edas_approved_by',
        'pdf_express_status', 'edas_error_note', 'revision_substatus',
    ];

    protected $hidden = ['author_token_hash', 'author_token_encrypted'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'status' => SubmissionStatus::class,
            'is_flagged_duplicate' => 'boolean',
            'initial_page_count' => 'integer',
            'final_page_count' => 'integer',
            'author_token_encrypted' => 'encrypted',
            'author_token_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'completed_at' => 'datetime',
            'deadline_at' => 'datetime', 'edas_submitted_at' => 'datetime', 'edas_approved_at' => 'datetime',
        ];
    }

    protected function correspondingAuthorPhone(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => PhoneNumber::parse($value) ?? $value
        );
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

    public function getSafeAuthorToken(): ?string
    {
        try {
            $raw = $this->getRawOriginal('author_token_encrypted');
            if (! is_string($raw) || $raw === '') {
                return null;
            }

            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function ensureValidAuthorToken(): string
    {
        $existing = $this->getSafeAuthorToken();
        if (is_string($existing)
            && $this->author_token_expires_at?->isFuture()
            && hash_equals((string) $this->author_token_hash, hash('sha256', $existing))) {
            return $existing;
        }

        $token = Str::random(64);
        $encryptedToken = Crypt::encryptString($token);
        $expiresAt = now()->addYear();

        DB::table('submissions')
            ->where('id', $this->id)
            ->update([
                'author_token_hash' => hash('sha256', $token),
                'author_token_encrypted' => $encryptedToken,
                'author_token_expires_at' => $expiresAt,
            ]);

        $this->attributes['author_token_hash'] = hash('sha256', $token);
        $this->attributes['author_token_encrypted'] = $encryptedToken;
        $this->author_token_expires_at = $expiresAt;
        $this->syncOriginal();

        return $token;
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

    public function portalLinkSent(): bool
    {
        if ($this->relationLoaded('emailLogs')) {
            return $this->emailLogs->contains(fn ($log) => in_array($log->template_key, ['submission_received', 'portal_access_link'], true) && $log->status !== 'failed');
        }

        return $this->emailLogs()
            ->whereIn('template_key', ['submission_received', 'portal_access_link'])
            ->where('status', '!=', 'failed')
            ->exists();
    }

    public function portalLinkSentAt(): ?Carbon
    {
        if ($this->relationLoaded('emailLogs')) {
            $log = $this->emailLogs->first(fn ($log) => in_array($log->template_key, ['submission_received', 'portal_access_link'], true) && $log->status !== 'failed');

            return $log?->sent_at ?? $log?->created_at;
        }

        $log = $this->emailLogs()
            ->whereIn('template_key', ['submission_received', 'portal_access_link'])
            ->where('status', '!=', 'failed')
            ->latest()
            ->first();

        return $log?->sent_at ?? $log?->created_at;
    }
}
