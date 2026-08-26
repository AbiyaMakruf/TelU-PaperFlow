<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledRevisionReminder extends Model
{
    use HasUlids;

    protected $fillable = ['conference_id', 'submission_id', 'editor_id', 'email_log_id', 'kind', 'recipient', 'deadline_date', 'scheduled_for', 'status', 'processed_at', 'cancelled_at', 'sent_at', 'reason', 'error'];

    protected function casts(): array
    {
        return ['deadline_date' => 'date', 'scheduled_for' => 'datetime', 'processed_at' => 'datetime', 'cancelled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }
}
