<?php

namespace App\Services;

use App\Models\Manual;
use App\Models\ManualChunk;
use App\Models\ManualExtractionCandidate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Throwable;

class ManualAiExtractionService
{
    public function enabled(): bool
    {
        return (bool) config('manual_ingestion.ai_extraction.enabled')
            && filled(config('prism.providers.gemini.api_key'));
    }

    public function maxChunksPerImport(): int
    {
        return max((int) config('manual_ingestion.ai_extraction.max_chunks_per_import', 40), 0);
    }

    public function shouldAnalyze(ManualChunk $chunk): bool
    {
        $content = Str::lower($chunk->content);

        return str_contains($content, 'chyb')
            || str_contains($content, 'error')
            || str_contains($content, 'fault')
            || str_contains($content, 'dtc')
            || str_contains($content, 'spn')
            || str_contains($content, 'fmi')
            || preg_match('/^\s*(?:0x[0-9a-f]{2,}|\d{1,6}|[a-z]{1,8}[- ]?\d{1,6})\b/miu', $chunk->content) === 1;
    }

    public function extractAndStore(
        Manual $manual,
        ManualChunk $chunk,
        int $pageNumber,
        ?string $sectionTitle,
        ?string $moduleKey,
    ): int {
        if (! $this->enabled()) {
            return 0;
        }

        try {
            $entries = $this->extract($manual, $chunk, $sectionTitle, $moduleKey);
        } catch (Throwable $throwable) {
            Log::warning('Gemini manual extraction failed.', [
                'manual_id' => $manual->id,
                'manual_chunk_id' => $chunk->id,
                'page_number' => $pageNumber,
                'message' => $throwable->getMessage(),
            ]);

            return 0;
        }

        $created = 0;

        foreach ($entries as $entry) {
            $identifiers = $this->normalizeIdentifiers($entry['identifiers'] ?? []);
            $primaryCode = $this->primaryCodeFromIdentifiers($identifiers);

            if ($primaryCode === null) {
                continue;
            }

            $confidence = $this->normalizeConfidence($entry['confidence'] ?? null);

            if ($confidence < (float) config('manual_ingestion.ai_extraction.min_confidence', 0.35)) {
                continue;
            }

            $context = array_filter(array_merge([
                'module' => $moduleKey,
                'section_title' => $sectionTitle,
                'manual_language' => $manual->language,
                'coverage_mode' => $manual->coverage_mode,
            ], is_array($entry['context'] ?? null) ? $entry['context'] : []));
            $entryModuleKey = $this->normalizeModuleKey((string) ($context['module'] ?? $moduleKey ?? '')) ?: $moduleKey;
            $meaning = $this->cleanText((string) ($entry['meaning'] ?? ''));

            if ($meaning === '') {
                continue;
            }

            if ($this->candidateExists($manual, $chunk, $primaryCode, $entryModuleKey, $meaning)) {
                continue;
            }

            $candidate = [
                'machine_id' => $manual->machine_id,
                'manual_id' => $manual->id,
                'manual_page_id' => $chunk->manual_page_id,
                'manual_chunk_id' => $chunk->id,
                'candidate_type' => isset($identifiers['spn']) || isset($identifiers['fmi'])
                    ? 'diagnostic_entry_ai_j1939'
                    : 'diagnostic_entry_ai',
                'code' => $primaryCode,
                'normalized_code' => $this->normalizeCode($primaryCode),
                'family' => $entryModuleKey,
                'module_key' => $entryModuleKey,
                'section_title' => $sectionTitle,
                'primary_code' => $primaryCode,
                'context' => $context,
                'identifiers' => $identifiers,
                'title' => Str::limit($meaning, 250, ''),
                'meaning' => $meaning,
                'cause' => $this->nullableCleanText($entry['cause'] ?? null),
                'recommended_action' => $this->nullableCleanText($entry['recommended_action'] ?? null),
                'source_text' => $this->nullableCleanText($entry['source_quote'] ?? null) ?: $chunk->content,
                'source_page_number' => $pageNumber,
                'extractor' => 'gemini_structured_chunk',
                'confidence' => $confidence,
                'metadata' => [
                    'ai_provider' => config('manual_ingestion.ai_extraction.provider'),
                    'ai_model' => config('manual_ingestion.ai_extraction.model'),
                    'chunk_heading' => $chunk->heading,
                    'section_title' => $sectionTitle,
                ],
            ];

            $candidate = ManualExtractionCandidate::create(
                array_merge($candidate, app(ManualExtractionCandidateReviewClassifier::class)->classify($candidate))
            );

            app(ManualExtractionCandidatePublishingService::class)->publish($candidate);

            $created++;
        }

        return $created;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extract(Manual $manual, ManualChunk $chunk, ?string $sectionTitle, ?string $moduleKey): array
    {
        $provider = $this->provider();
        $model = (string) config('manual_ingestion.ai_extraction.model', 'gemini-2.0-flash');
        $content = Str::limit(
            $chunk->content,
            (int) config('manual_ingestion.ai_extraction.max_chunk_characters', 6000),
            ''
        );

        $response = Prism::structured()
            ->using($provider, $model)
            ->withSchema($this->schema())
            ->withSystemPrompt($this->systemPrompt())
            ->withPrompt($this->prompt($manual, $content, $sectionTitle, $moduleKey))
            ->usingTemperature(0.1)
            ->withMaxTokens(3000)
            ->asStructured();

        $structured = $response->structured ?? [];

        if (! is_array($structured['entries'] ?? null)) {
            return [];
        }

        return $structured['entries'];
    }

    private function provider(): Provider|string
    {
        $provider = (string) config('manual_ingestion.ai_extraction.provider', 'gemini');

        return Provider::tryFrom($provider) ?? $provider;
    }

    private function schema(): ObjectSchema
    {
        $contextSchema = new ObjectSchema(
            name: 'context',
            description: 'Context from the manual section, such as module, subsystem, protocol, software version, or section title.',
            properties: [
                new StringSchema('module', 'Normalized module or subsystem name, if present.', nullable: true),
                new StringSchema('section_title', 'Manual section title, if present.', nullable: true),
            ],
            requiredFields: [],
            allowAdditionalProperties: true,
        );

        $identifiersSchema = new ObjectSchema(
            name: 'identifiers',
            description: 'Flexible diagnostic identifiers. Use keys from the manual, for example code, spn, fmi, sad, sad_mps, sad_merlotool.',
            properties: [
                new StringSchema('code', 'Main visible error code, if present.', nullable: true),
                new StringSchema('spn', 'J1939 SPN, if present.', nullable: true),
                new StringSchema('fmi', 'J1939 FMI, if present.', nullable: true),
                new StringSchema('sad', 'SAD value, if present.', nullable: true),
            ],
            requiredFields: [],
            allowAdditionalProperties: true,
        );

        $entrySchema = new ObjectSchema(
            name: 'entry',
            description: 'One diagnostic/error entry extracted from the manual text.',
            properties: [
                $contextSchema,
                $identifiersSchema,
                new StringSchema('meaning', 'What the diagnostic entry means.', nullable: true),
                new StringSchema('cause', 'Likely cause if stated.', nullable: true),
                new StringSchema('recommended_action', 'Repair/check action if stated.', nullable: true),
                new StringSchema('severity', 'Severity if stated.', nullable: true),
                new StringSchema('source_quote', 'Short exact source excerpt supporting the entry.', nullable: true),
                new NumberSchema('confidence', 'Confidence from 0.0 to 1.0.', minimum: 0.0, maximum: 1.0),
            ],
            requiredFields: ['context', 'identifiers', 'meaning', 'source_quote', 'confidence'],
        );

        return new ObjectSchema(
            name: 'manual_diagnostic_entries',
            description: 'Diagnostic entries extracted from a manual section without assuming a fixed table layout.',
            properties: [
                new ArraySchema(
                    name: 'entries',
                    description: 'Diagnostic entries found in the supplied manual text.',
                    items: $entrySchema,
                ),
            ],
            requiredFields: ['entries'],
        );
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You extract diagnostic knowledge from machine service manuals.

Return only entries that are explicitly supported by the supplied text.
Do not assume a fixed table layout.
Use generic identifiers:
- simple code tables: identifiers.code
- J1939 rows: identifiers.spn, identifiers.fmi, identifiers.sad, identifiers.sad_mps, identifiers.sad_merlotool when present
- other table columns: keep the manual column name as a snake_case identifier key

Preserve the manual language for meaning and recommended_action.
If text is ambiguous or only a table header, do not create an entry.
PROMPT;
    }

    private function prompt(Manual $manual, string $content, ?string $sectionTitle, ?string $moduleKey): string
    {
        return sprintf(
            "Manual: %s\nLanguage: %s\nSection title: %s\nModule key: %s\n\nExtract diagnostic entries from this text:\n\n%s",
            $manual->title,
            $manual->language,
            $sectionTitle ?: 'unknown',
            $moduleKey ?: 'unknown',
            $content,
        );
    }

    /**
     * @param  array<mixed>  $identifiers
     * @return array<string, string>
     */
    private function normalizeIdentifiers(array $identifiers): array
    {
        $normalized = [];

        foreach ($identifiers as $key => $value) {
            if (! is_scalar($value) || $value === '') {
                continue;
            }

            $normalizedKey = Str::snake((string) $key);
            $normalizedValue = $this->cleanText((string) $value);

            if ($normalizedKey === 'code') {
                $normalizedValue = $this->normalizeCode($normalizedValue);
            }

            if (in_array($normalizedKey, ['spn', 'fmi', 'sad_mps'], true)) {
                $normalizedValue = $this->normalizeNumericIdentifier($normalizedValue);
            }

            if (in_array($normalizedKey, ['sad', 'sad_merlotool'], true)) {
                $normalizedValue = Str::upper($normalizedValue);
            }

            $normalized[$normalizedKey] = $normalizedValue;
        }

        if (! isset($normalized['code']) && isset($normalized['spn'])) {
            $normalized['code'] = $normalized['spn'];
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $identifiers
     */
    private function primaryCodeFromIdentifiers(array $identifiers): ?string
    {
        return $identifiers['code'] ?? $identifiers['spn'] ?? null;
    }

    private function normalizeConfidence(mixed $confidence): float
    {
        if (! is_numeric($confidence)) {
            return 0.5;
        }

        return round(max(min((float) $confidence, 1.0), 0.0), 4);
    }

    private function candidateExists(Manual $manual, ManualChunk $chunk, string $primaryCode, ?string $moduleKey, string $meaning): bool
    {
        return ManualExtractionCandidate::query()
            ->where('manual_id', $manual->id)
            ->where('manual_chunk_id', $chunk->id)
            ->where('normalized_code', $this->normalizeCode($primaryCode))
            ->when($moduleKey, fn ($query) => $query->where('module_key', $moduleKey))
            ->where('meaning', $meaning)
            ->exists();
    }

    private function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/[\s-]+/u', '', trim($code)) ?? trim($code));
    }

    private function normalizeModuleKey(string $module): string
    {
        return Str::upper(preg_replace('/[^A-Z0-9]+/iu', '', $module) ?? $module);
    }

    private function normalizeNumericIdentifier(string $value): string
    {
        $trimmed = ltrim(preg_replace('/\D+/u', '', $value) ?? $value, '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    private function nullableCleanText(mixed $text): ?string
    {
        if (! is_scalar($text)) {
            return null;
        }

        $cleaned = $this->cleanText((string) $text);

        return $cleaned !== '' ? $cleaned : null;
    }

    private function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
