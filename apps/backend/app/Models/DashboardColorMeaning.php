<?php

namespace App\Models;

use App\Services\DashboardColorAliasGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DashboardColorMeaning extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'label',
        'ai_key',
        'hex_color',
        'ai_aliases',
        'description',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ai_aliases' => 'array',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DashboardColorMeaning $meaning): void {
            $meaning->hex_color = static::normalizeHexColor($meaning->hex_color);
            $meaning->ai_key = static::normalizeAiKey($meaning->label);

            if ($meaning->isDirty('hex_color') || blank($meaning->ai_aliases)) {
                $meaning->ai_aliases = app(DashboardColorAliasGenerator::class)->aliasesForHex($meaning->hex_color);
            }
        });
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function diagnosisCandidates(): HasMany
    {
        return $this->hasMany(DiagnosisCandidate::class);
    }

    public static function normalizeAiKey(string $key): string
    {
        return Str::of($key)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    private static function normalizeHexColor(string $hex): string
    {
        $normalized = ltrim(trim($hex), '#');

        if (strlen($normalized) === 3 && preg_match('/^[0-9a-f]{3}$/i', $normalized)) {
            $normalized = implode('', array_map(
                fn (string $character): string => $character.$character,
                str_split($normalized),
            ));
        }

        return '#'.Str::upper($normalized);
    }
}
