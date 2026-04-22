<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Machine extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'manufacturer',
        'model_number',
        'description',
        'dashboard_notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function codePatterns(): HasMany
    {
        return $this->hasMany(MachineCodePattern::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function softwareVersions(): HasMany
    {
        return $this->hasMany(SoftwareVersion::class);
    }

    public function manuals(): HasMany
    {
        return $this->hasMany(Manual::class);
    }

    public function errorCodes(): HasMany
    {
        return $this->hasMany(ErrorCode::class);
    }

    public function diagnosticAliases(): HasMany
    {
        return $this->hasMany(DiagnosticAlias::class);
    }

    public function diagnosticEntries(): HasMany
    {
        return $this->hasMany(DiagnosticEntry::class);
    }

    public function extractionCandidates(): HasMany
    {
        return $this->hasMany(ManualExtractionCandidate::class);
    }

}
