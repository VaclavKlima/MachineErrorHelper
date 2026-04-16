<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manual extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'software_version_id',
        'title',
        'coverage_mode',
        'language',
        'file_path',
        'file_hash',
        'page_count',
        'published_at',
        'source_notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function softwareVersion(): BelongsTo
    {
        return $this->belongsTo(SoftwareVersion::class);
    }

    public function importRuns(): HasMany
    {
        return $this->hasMany(ManualImportRun::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(ManualPage::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ManualChunk::class);
    }

    public function extractionCandidates(): HasMany
    {
        return $this->hasMany(ManualExtractionCandidate::class);
    }

    public function definitions(): HasMany
    {
        return $this->hasMany(ErrorCodeDefinition::class);
    }

    public function diagnosticEntries(): HasMany
    {
        return $this->hasMany(DiagnosticEntry::class);
    }
}
