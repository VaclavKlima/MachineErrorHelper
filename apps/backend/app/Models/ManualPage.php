<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'manual_id',
        'page_number',
        'text',
        'ocr_text',
        'image_path',
        'extraction_quality',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'extraction_quality' => 'float',
        ];
    }

    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ManualChunk::class);
    }

    public function extractionCandidates(): HasMany
    {
        return $this->hasMany(ManualExtractionCandidate::class);
    }
}
