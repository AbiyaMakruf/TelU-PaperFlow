<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    use HasUlids;

    protected $fillable = ['conference_id', 'key', 'subject', 'body', 'default_cc', 'is_enabled'];

    protected function casts(): array
    {
        return ['default_cc' => 'array', 'is_enabled' => 'boolean'];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }
}
