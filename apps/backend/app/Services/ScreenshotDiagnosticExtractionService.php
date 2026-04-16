<?php

namespace App\Services;

use App\Models\DiagnosticEntry;
use App\Models\DiagnosisRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Media\Image;

class ScreenshotDiagnosticExtractionService
{
    /**
     * @return array{
     *     module_key: string|null,
     *     software_version: string|null,
     *     serial_number: string|null,
     *     raw_text: string|null,
     *     errors: array<int, array<string, mixed>>
     * }
     */
    public function extract(DiagnosisRequest $diagnosis): array
    {
        $path = $diagnosis->screenshot_path ? Storage::disk('local')->path($diagnosis->screenshot_path) : null;

        if (! $path || ! is_file($path)) {
            throw new \RuntimeException('Diagnosis screenshot file does not exist.');
        }

        $response = Prism::structured()
            ->using($this->provider(), (string) env('SCREENSHOT_AI_MODEL', 'gemini-2.0-flash'))
            ->withSchema($this->schema())
            ->withSystemPrompt($this->systemPrompt())
            ->withPrompt($this->prompt(), [
                Image::fromLocalPath($path),
            ])
            ->usingTemperature(0.0)
            ->withMaxTokens(1600)
            ->withClientOptions(['timeout' => (int) env('SCREENSHOT_AI_TIMEOUT', 60)])
            ->asStructured();

        $structured = is_array($response->structured ?? null) ? $response->structured : [];

        return [
            'module_key' => $this->nullableCleanText($structured['module_key'] ?? null),
            'software_version' => $this->nullableCleanText($structured['software_version'] ?? null),
            'serial_number' => $this->nullableCleanText($structured['serial_number'] ?? null),
            'raw_text' => $this->nullableCleanText($structured['raw_text'] ?? null),
            'errors' => $this->normalizeErrors(is_array($structured['errors'] ?? null) ? $structured['errors'] : []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     * @return array<int, array<string, mixed>>
     */
    public function storeCandidates(DiagnosisRequest $diagnosis, array $extraction): array
    {
        $diagnosis->candidates()->delete();

        $created = [];
        $seen = [];
        $defaultModuleKey = $this->normalizeModuleKey((string) ($extraction['module_key'] ?? ''));

        foreach ($extraction['errors'] ?? [] as $error) {
            $code = $this->normalizeCode((string) ($error['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            $moduleKey = $this->normalizeModuleKey((string) ($error['module_key'] ?? $defaultModuleKey));
            $dedupeKey = $moduleKey.'|'.$code;

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            [$match, $matchingStrategy] = $this->matchDiagnosticEntry($diagnosis, $code, $moduleKey);

            $created[] = $diagnosis->candidates()->create([
                'candidate_code' => (string) ($error['code'] ?? $code),
                'normalized_code' => $code,
                'source' => 'gemini_screenshot',
                'confidence' => $this->normalizeConfidence($error['confidence'] ?? 0.7),
                'matched_diagnostic_entry_id' => $match?->id,
                'metadata' => array_filter([
                    'module_key' => $moduleKey ?: null,
                    'display_text' => $this->nullableCleanText($error['display_text'] ?? null),
                    'label' => $this->nullableCleanText($error['label'] ?? null),
                    'color' => $this->nullableCleanText($error['color'] ?? null),
                    'software_version' => $this->nullableCleanText($extraction['software_version'] ?? null),
                    'serial_number' => $this->nullableCleanText($extraction['serial_number'] ?? null),
                    'matching_strategy' => $matchingStrategy,
                ]),
            ]);
        }

        return $created;
    }

    private function provider(): Provider|string
    {
        $provider = (string) env('SCREENSHOT_AI_PROVIDER', 'gemini');

        return Provider::tryFrom($provider) ?? $provider;
    }

    private function schema(): ObjectSchema
    {
        $errorSchema = new ObjectSchema(
            name: 'visible_error',
            description: 'One visible error or alarm code on the dashboard.',
            properties: [
                new StringSchema('code', 'The visible error code, usually numbers or letters plus numbers.', nullable: false),
                new StringSchema('module_key', 'Module/subsystem shown near the code, if a specific one is visible.', nullable: true),
                new StringSchema('display_text', 'Exact short visible text near this error.', nullable: true),
                new StringSchema('label', 'Nearby label such as error, alarm, DTC, active, stored.', nullable: true),
                new StringSchema('color', 'Visible background/text color for this error, such as red, purple, yellow, orange.', nullable: true),
                new NumberSchema('confidence', 'Confidence that this visible item is an actual active error code, 0.0 to 1.0.', minimum: 0.0, maximum: 1.0),
            ],
            requiredFields: ['code', 'confidence'],
        );

        return new ObjectSchema(
            name: 'dashboard_error_extraction',
            description: 'Machine dashboard OCR and visible error code extraction.',
            properties: [
                new StringSchema('module_key', 'Main dashboard module/controller name, normalized as visible text. Example PLUG_SA or CU533.', nullable: true),
                new StringSchema('software_version', 'Software version visible on the dashboard, for example SW:1.5.0.', nullable: true),
                new StringSchema('serial_number', 'Serial number if visible.', nullable: true),
                new StringSchema('raw_text', 'Short OCR transcription of important visible dashboard text.', nullable: true),
                new ArraySchema('errors', 'All visible active error/alarm codes. Include multiple codes when visible.', $errorSchema),
            ],
            requiredFields: ['errors'],
        );
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You read screenshots of machine dashboards for a machinist.

Extract all visible diagnostic/error/alarm codes. Do not stop at the first code.
Codes can be shown as colored badges, table cells, alarm lists, or module diagnostics.
Also extract the visible module/controller text and software version when present.

Rules:
- Return only codes visible in the image.
- Do not invent meanings or repair steps.
- Keep each visible code as a separate item.
- If the module appears as PLUG_SA, keep that text. The backend will normalize it.
- If two codes are visible, return two errors.
- Ignore normal labels, menu numbers, timestamps, page numbers, and values that are clearly not errors.
PROMPT;
    }

    private function prompt(): string
    {
        return 'Read this dashboard screenshot and extract module, software version, serial number, OCR text, and all visible error codes.';
    }

    /**
     * @param  array<int, mixed>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function normalizeErrors(array $errors): array
    {
        $normalized = [];

        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue;
            }

            $code = $this->normalizeCode((string) ($error['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            $normalized[] = [
                'code' => $code,
                'module_key' => $this->nullableCleanText($error['module_key'] ?? null),
                'display_text' => $this->nullableCleanText($error['display_text'] ?? null),
                'label' => $this->nullableCleanText($error['label'] ?? null),
                'color' => $this->nullableCleanText($error['color'] ?? null),
                'confidence' => $this->normalizeConfidence($error['confidence'] ?? 0.7),
            ];
        }

        return $normalized;
    }

    /**
     * @return array{0: DiagnosticEntry|null, 1: string}
     */
    private function matchDiagnosticEntry(DiagnosisRequest $diagnosis, string $code, ?string $moduleKey): array
    {
        $baseQuery = DiagnosticEntry::query()
            ->where('machine_id', $diagnosis->machine_id)
            ->where('status', 'approved')
            ->whereIn('primary_code_normalized', $this->codeMatchVariants($code));

        if ($moduleKey) {
            $moduleMatch = (clone $baseQuery)
                ->where('module_key', $moduleKey)
                ->orderByDesc('confidence')
                ->first();

            if ($moduleMatch) {
                return [$moduleMatch, 'module_and_code'];
            }
        }

        return [
            $baseQuery->orderByDesc('confidence')->first(),
            'code_only',
        ];
    }

    private function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/[\s-]+/u', '', trim($code)) ?? trim($code));
    }

    /**
     * @return array<int, string>
     */
    private function codeMatchVariants(string $normalizedCode): array
    {
        $variants = [$normalizedCode];

        if (preg_match('/^\d+$/', $normalizedCode) === 1) {
            $withoutLeadingZeroes = ltrim($normalizedCode, '0');
            $variants[] = $withoutLeadingZeroes === '' ? '0' : $withoutLeadingZeroes;
        }

        return array_values(array_unique($variants));
    }

    private function normalizeModuleKey(string $module): ?string
    {
        $normalized = Str::upper(preg_replace('/[^A-Z0-9]+/iu', '', trim($module)) ?? trim($module));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeConfidence(mixed $confidence): float
    {
        if (! is_numeric($confidence)) {
            return 0.7;
        }

        return round(max(min((float) $confidence, 1.0), 0.0), 4);
    }

    private function nullableCleanText(mixed $text): ?string
    {
        if (! is_scalar($text)) {
            return null;
        }

        $cleaned = trim(preg_replace('/\s+/u', ' ', (string) $text) ?? (string) $text);

        return $cleaned !== '' ? $cleaned : null;
    }
}
