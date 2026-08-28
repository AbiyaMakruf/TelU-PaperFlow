<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FileVersion extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'submission_id', 'based_on_file_version_id', 'version_number', 'label', 'source', 'disk', 'storage_path',
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

    public function editorialBaseFile(): BelongsTo
    {
        return $this->belongsTo(self::class, 'based_on_file_version_id');
    }

    public function downloadNameFor(Submission $submission): string
    {
        $paperId = Str::slug((string) ($submission->paper_id ?: $submission->paper_code ?: 'paper'));
        $source = match ($this->source) {
            'editorial' => 'editorial',
            'author' => 'author',
            default => Str::slug((string) $this->source) ?: 'file',
        };
        $suffix = $this->file_category === 'revision_guidance_pdf' ? '-guidance' : '';
        $extension = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
        $filename = "{$paperId}-v{$this->version_number}-{$source}{$suffix}";

        return $extension !== '' ? "{$filename}.{$extension}" : $filename;
    }
}
