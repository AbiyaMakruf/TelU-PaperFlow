<?php

namespace App\Models;

use App\Enums\ReviewStage;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplate extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['conference_id', 'name', 'stage', 'is_active'];

    protected function casts(): array
    {
        return ['stage' => ReviewStage::class, 'is_active' => 'boolean'];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort_order');
    }
}
