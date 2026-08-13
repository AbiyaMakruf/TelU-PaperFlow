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

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', $value)->orWhere('slug', $value)->firstOrFail();
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

    public function brandBannerUrl(): ?string
    {
        return isset($this->settings['brand_banner']) ? asset('storage/'.$this->settings['brand_banner']) : null;
    }

    public function formTitle(): string
    {
        return ! empty($this->settings['form_title'])
            ? $this->settings['form_title']
            : ($this->name.': Final Manuscript & Materials Submission');
    }

    public function formDescription(): string
    {
        return ! empty($this->settings['form_description'])
            ? $this->settings['form_description']
            : ('Thank you for your contribution to '.$this->name.'. Please use this form to submit your final, camera-ready manuscript and all required supplementary materials. Ensuring your submission strictly adheres to the conference formatting guidelines will facilitate a smooth publication process.');
    }

    public function editorialGuidelinesUrl(): ?string
    {
        return ! empty($this->settings['editorial_guidelines_url'])
            ? $this->settings['editorial_guidelines_url']
            : null;
    }

    /** @return list<string> */
    public function defaultCc(): array
    {
        return array_values(array_filter($this->settings['default_cc'] ?? [], fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL)));
    }

    public function submissionMode(): string
    {
        return $this->settings['submission_mode'] ?? 'paperflow_native';
    }

    public function isGoogleFormMode(): bool
    {
        return $this->submissionMode() === 'google_form_external';
    }

    /** @return array<string, mixed> */
    public function googleFormMapping(): array
    {
        $defaults = [
            'paper_id_column' => 'ID Papers (#)',
            'title_column' => "Paper's Title",
            'author_name_column' => "Registered Author's Name",
            'author_email_column' => "Registered Author's Email Address",
            'author_phone_column' => "Registered Author's Phone Number",
            'manuscript_file_column' => 'Upload the Manuscript Source',
            'custom_fields' => [
                ['label' => 'Presenter Name', 'column' => 'Name of Presenter'],
                ['label' => 'Revision Form Link', 'column' => 'Upload the Revision Form'],
                ['label' => 'Similarity Report Link', 'column' => 'Upload the Simmilarity Report'],
            ],
        ];

        $saved = $this->settings['google_form_mapping'] ?? [];
        $mapping = array_merge($defaults, $saved);

        if (isset($saved['custom_fields']) && is_array($saved['custom_fields'])) {
            $mapping['custom_fields'] = array_values(array_filter($saved['custom_fields'], function ($item) {
                return is_array($item) && filled($item['label'] ?? null) && filled($item['column'] ?? null);
            }));
        }

        return $mapping;
    }
}
