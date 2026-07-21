<?php

namespace App\Models;

use App\Enums\ConferenceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conference extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'status', 'timezone', 'starts_at', 'ends_at',
        'submission_opens_at', 'submission_closes_at', 'settings', 'created_by',
        'google_drive_token', 'google_drive_folder_id', 'google_drive_connected_at',
        'storage_provider',
        'email_sender_name',
    ];

    protected $hidden = ['google_drive_token'];

    protected function casts(): array
    {
        return [
            'status' => ConferenceStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'submission_opens_at' => 'datetime',
            'submission_closes_at' => 'datetime',
            'settings' => 'array',
            'google_drive_token' => 'encrypted:array',
            'google_drive_connected_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ConferenceMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conference_members')
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function formVersions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function checklistTemplates(): HasMany
    {
        return $this->hasMany(ChecklistTemplate::class);
    }

    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    public function publishedForm(): ?FormVersion
    {
        return $this->formVersions()->where('status', 'published')->latest('version')->first();
    }

    public function isSubmissionOpen(): bool
    {
        if ($this->status !== ConferenceStatus::Active) {
            return false;
        }

        $now = now($this->timezone);

        return (! $this->submission_opens_at || $now->greaterThanOrEqualTo($this->submission_opens_at))
            && (! $this->submission_closes_at || $now->lessThanOrEqualTo($this->submission_closes_at));
    }

    public function usesGoogleDrive(): bool
    {
        return $this->storage_provider === 'google_drive';
    }

    public function allowedFileExtensions(bool $includePdf = false): array
    {
        $defaults = $includePdf ? ['doc', 'docx', 'tex', 'zip', 'pdf'] : ['doc', 'docx', 'tex', 'zip'];

        return array_values(array_intersect($this->settings['allowed_extensions'] ?? $defaults, $defaults));
    }

    public function maxFileSizeMb(): int
    {
        return max(1, min(100, (int) ($this->settings['max_file_mb'] ?? 25)));
    }

    public function brandPrimary(): string
    {
        return $this->settings['brand_primary'] ?? '#102a43';
    }

    public function brandAccent(): string
    {
        return $this->settings['brand_accent'] ?? '#f47c20';
    }

    public function brandLogoUrl(): ?string
    {
        return isset($this->settings['brand_logo']) ? asset('storage/'.$this->settings['brand_logo']) : null;
    }
}
