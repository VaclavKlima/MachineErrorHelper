<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorCodeDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'error_code_id',
        'manual_id',
        'manual_chunk_id',
        'effective_from_version_id',
        'effective_to_version_id',
        'supersedes_definition_id',
        'source_page_number',
        'title',
        'meaning',
        'cause',
        'severity',
        'recommended_action',
        'source_confidence',
        'approval_status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'source_confidence' => 'float',
            'source_page_number' => 'integer',
        ];
    }

    public function errorCode(): BelongsTo
    {
        return $this->belongsTo(ErrorCode::class);
    }

    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class);
    }

    public function manualChunk(): BelongsTo
    {
        return $this->belongsTo(ManualChunk::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
