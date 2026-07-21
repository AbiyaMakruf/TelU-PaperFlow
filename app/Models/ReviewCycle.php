<?php

namespace App\Models;

use App\Enums\ReviewStage;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewCycle extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'submission_id', 'checklist_template_id', 'stage', 'cycle_number', 'status',
        'assigned_to', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['stage' => ReviewStage::class, 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ReviewItemResult::class);
    }
}
