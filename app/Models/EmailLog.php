<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasUlids;

    protected $fillable = [
        'conference_id', 'submission_id', 'template_key', 'recipient', 'cc', 'subject',
        'status', 'attempts', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['cc' => 'array', 'sent_at' => 'datetime'];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
