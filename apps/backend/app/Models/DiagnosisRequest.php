<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiagnosisRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'machine_id',
        'user_id',
        'software_version_id',
        'selected_error_code_id',
        'selected_definition_id',
        'selected_diagnostic_entry_id',
        'screenshot_path',
        'status',
        'raw_ocr_text',
        'confidence',
        'result_payload',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'result_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DiagnosisRequest $diagnosisRequest): void {
            $diagnosisRequest->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function softwareVersion(): BelongsTo
    {
        return $this->belongsTo(SoftwareVersion::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(DiagnosisCandidate::class);
    }

    public function selectedDiagnosticEntry(): BelongsTo
    {
        return $this->belongsTo(DiagnosticEntry::class, 'selected_diagnostic_entry_id');
    }
}
