<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosisCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'diagnosis_request_id',
        'matched_error_code_id',
        'matched_definition_id',
        'matched_diagnostic_entry_id',
        'dashboard_color_meaning_id',
        'candidate_code',
        'normalized_code',
        'source',
        'confidence',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'metadata' => 'array',
        ];
    }

    public function diagnosisRequest(): BelongsTo
    {
        return $this->belongsTo(DiagnosisRequest::class);
    }

    public function matchedErrorCode(): BelongsTo
    {
        return $this->belongsTo(ErrorCode::class, 'matched_error_code_id');
    }

    public function matchedDefinition(): BelongsTo
    {
        return $this->belongsTo(ErrorCodeDefinition::class, 'matched_definition_id');
    }

    public function matchedDiagnosticEntry(): BelongsTo
    {
        return $this->belongsTo(DiagnosticEntry::class, 'matched_diagnostic_entry_id');
    }

    public function dashboardColorMeaning(): BelongsTo
    {
        return $this->belongsTo(DashboardColorMeaning::class);
    }
}
