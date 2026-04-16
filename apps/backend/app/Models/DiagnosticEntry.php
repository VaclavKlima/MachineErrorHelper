<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'manual_id',
        'manual_page_id',
        'manual_chunk_id',
        'manual_extraction_candidate_id',
        'effective_from_version_id',
        'effective_to_version_id',
        'module_key',
        'section_title',
        'primary_code',
        'primary_code_normalized',
        'context',
        'identifiers',
        'title',
        'meaning',
        'cause',
        'severity',
        'recommended_action',
        'source_text',
        'source_page_number',
        'extractor',
        'confidence',
        'status',
        'approved_by',
        'approved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'identifiers' => 'array',
            'metadata' => 'array',
            'confidence' => 'float',
            'source_page_number' => 'integer',
            'approved_at' => 'datetime',
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

    public function sourceCandidate(): BelongsTo
    {
        return $this->belongsTo(ManualExtractionCandidate::class, 'manual_extraction_candidate_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
