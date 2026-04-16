<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualExtractionCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'manual_id',
        'manual_page_id',
        'manual_chunk_id',
        'candidate_type',
        'code',
        'normalized_code',
        'family',
        'module_key',
        'section_title',
        'primary_code',
        'context',
        'identifiers',
        'title',
        'meaning',
        'cause',
        'recommended_action',
        'source_text',
        'source_page_number',
        'extractor',
        'confidence',
        'review_score',
        'review_priority',
        'noise_reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'review_score' => 'float',
            'context' => 'array',
            'identifiers' => 'array',
            'metadata' => 'array',
            'reviewed_at' => 'datetime',
            'source_page_number' => 'integer',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class);
    }

    public function manualPage(): BelongsTo
    {
        return $this->belongsTo(ManualPage::class);
    }

    public function manualChunk(): BelongsTo
    {
        return $this->belongsTo(ManualChunk::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
