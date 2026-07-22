<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ConferenceRole;
use App\Notifications\PaperflowResetPassword;
use App\Services\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_super_admin',
        'is_active',
        'must_change_password',
        'locale',
        'whatsapp_country_code',
        'whatsapp_number',
        'job_title',
        'affiliation',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function conferenceMemberships(): HasMany
    {
        return $this->hasMany(ConferenceMember::class);
    }

    public function conferences(): BelongsToMany
    {
        return $this->belongsToMany(Conference::class, 'conference_members')
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin && $this->is_active;
    }

    public function hasConferenceRole(Conference|string $conference, ConferenceRole|string ...$roles): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $conferenceId = $conference instanceof Conference ? $conference->getKey() : $conference;
        $roleValues = collect($roles)->map(fn (ConferenceRole|string $role) => $role instanceof ConferenceRole ? $role->value : $role);

        return $this->conferenceMemberships()
            ->where('conference_id', $conferenceId)
            ->where('is_active', true)
            ->whereIn('role', $roleValues)
            ->exists();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PaperflowResetPassword($token));
    }

    public function whatsapp(): ?string
    {
        if (! $this->whatsapp_country_code || ! $this->whatsapp_number) {
            return null;
        }

        return PhoneNumber::normalize($this->whatsapp_country_code, $this->whatsapp_number);
    }
}
