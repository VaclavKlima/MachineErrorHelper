<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiagnosticEntry extends Model
{
    use HasFactory;
    use SoftDeletes;

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
            'deleted_at' => 'datetime',
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

    public function codeDocumentations(): BelongsToMany
    {
        return $this->belongsToMany(CodeDocumentation::class, 'code_documentation_diagnostic_entry')
            ->withTimestamps();
    }

    protected function documentationLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = array_filter([
                $this->machine?->name,
                $this->module_key ? "Module {$this->module_key}" : null,
                $this->primary_code ?: $this->primary_code_normalized,
                $this->title,
            ], fn (?string $value): bool => filled($value));

            return implode(' | ', array_unique($parts));
        });
    }

    public function scopeSearchForDocumentation(Builder $query, string $search): Builder
    {
        $terms = collect(preg_split('/[\s,;|]+/u', trim($search)) ?: [])
            ->map(fn (string $term): string => trim($term))
            ->filter()
            ->values();

        if ($terms->isEmpty()) {
            return $query;
        }

        foreach ($terms as $term) {
            $rawLike = '%'.mb_strtolower($term).'%';
            $compactLike = '%'.self::normalizeDocumentationSearchTerm($term).'%';

            $query->where(function (Builder $termQuery) use ($rawLike, $compactLike): void {
                foreach (['primary_code', 'primary_code_normalized', 'module_key', 'title', 'section_title'] as $column) {
                    $termQuery->orWhereRaw('lower(coalesce('.$column.", '')) like ?", [$rawLike]);
                    $termQuery->orWhereRaw(self::documentationSearchExpression($column).' like ?', [$compactLike]);
                }

                $termQuery->orWhereHas('machine', function (Builder $machineQuery) use ($rawLike, $compactLike): void {
                    $machineQuery
                        ->whereRaw("lower(coalesce(name, '')) like ?", [$rawLike])
                        ->orWhereRaw(self::documentationSearchExpression('name').' like ?', [$compactLike]);
                });
            });
        }

        return $query;
    }

    private static function normalizeDocumentationSearchTerm(string $value): string
    {
        return mb_strtolower(preg_replace('/[^[:alnum:]]+/u', '', $value) ?? $value);
    }

    private static function documentationSearchExpression(string $column): string
    {
        return "lower(replace(replace(replace(replace(replace(coalesce({$column}, ''), ' ', ''), '-', ''), '/', ''), '_', ''), '.', ''))";
    }
}
