<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileVersion extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'submission_id', 'version_number', 'label', 'source', 'disk', 'storage_path',
        'original_name', 'mime_type', 'size', 'file_hash', 'checksum', 'uploaded_by', 'notes', 'is_final',
        'external_provider', 'external_id', 'external_url', 'file_category',
    ];

    protected function casts(): array
    {
        return ['is_final' => 'boolean'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
