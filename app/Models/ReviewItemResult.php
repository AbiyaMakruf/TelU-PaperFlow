<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewItemResult extends Model
{
    use HasFactory;

    protected $fillable = ['review_cycle_id', 'checklist_item_id', 'is_checked', 'note', 'checked_by', 'checked_at'];

    protected function casts(): array
    {
        return ['is_checked' => 'boolean', 'checked_at' => 'datetime'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ReviewCycle::class, 'review_cycle_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }
}
