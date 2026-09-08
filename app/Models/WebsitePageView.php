<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsitePageView extends Model
{
    protected $fillable = [
        'conference_id',
        'visitor_hash',
        'path',
        'page_type',
        'method',
        'status_code',
        'referrer',
        'referrer_type',
        'device_type',
        'browser',
        'platform',
        'visited_at',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'status_code' => 'integer',
        ];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function scopeInPeriod(Builder $query, ?string $from = null, ?string $to = null): Builder
    {
        if ($from) {
            $query->whereDate('visited_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('visited_at', '<=', $to);
        }

        return $query;
    }

    public function scopeByConference(Builder $query, ?string $conferenceId = null): Builder
    {
        if ($conferenceId) {
            $query->where('conference_id', $conferenceId);
        }

        return $query;
    }
}
