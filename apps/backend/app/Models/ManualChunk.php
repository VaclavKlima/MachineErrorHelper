<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'manual_id',
        'manual_page_id',
        'chunk_index',
        'heading',
        'content',
        'content_hash',
        'embedding',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }

    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(ManualPage::class, 'manual_page_id');
    }

    public function extractionCandidates(): HasMany
    {
        return $this->hasMany(ManualExtractionCandidate::class);
    }

    public function diagnosticEntries(): HasMany
    {
        return $this->hasMany(DiagnosticEntry::class);
    }
}
