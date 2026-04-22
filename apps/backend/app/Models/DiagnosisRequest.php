<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

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
        'ai_detected_codes',
        'user_entered_codes',
        'confidence',
        'result_payload',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'ai_detected_codes' => 'array',
            'user_entered_codes' => 'array',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(DiagnosisCandidate::class);
    }

    public function selectedDiagnosticEntry(): BelongsTo
    {
        return $this->belongsTo(DiagnosticEntry::class, 'selected_diagnostic_entry_id');
    }

    protected function screenshotUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! filled($this->screenshot_path)) {
                return null;
            }

            try {
                return Storage::disk('local')->temporaryUrl(
                    $this->screenshot_path,
                    now()->addMinutes(30),
                );
            } catch (Throwable) {
                return Storage::disk('local')->url($this->screenshot_path);
            }
        });
    }
}
