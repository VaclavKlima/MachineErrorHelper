<?php

namespace App\Services;

use App\Models\DiagnosisRequest;
use App\Models\DiagnosticEntry;
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
     *     controller_identifier: string|null,
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
            ->using($this->provider(), (string) env('SCREENSHOT_AI_MODEL', 'gemini-2.5-pro'))
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
            'controller_identifier' => $this->nullableCleanText($structured['controller_identifier'] ?? null),
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
                    'controller_identifier' => $this->nullableCleanText($extraction['controller_identifier'] ?? null),
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
                new StringSchema('module_key', 'Main dashboard module name only, usually the text before a dash. Examples: PLUG_SA, UGSS_S, UGM, UCTI. Do not include controller ids such as CU533 or 125971.', nullable: true),
                new StringSchema('controller_identifier', 'Controller, ECU, or unit identifier visible after the module dash, for example CU533, 117006, 125971, or 14871.', nullable: true),
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

Common screen layout:
- The top green header often contains "MODULE - controller/id", for example "PLUG_SA - CU533", "UGSS_S - 117006", "UGM - 14871", or "UCTI - 124512".
- Set module_key to only the module part before the dash: PLUG_SA, UGSS_S, UGM, UCTI.
- Set controller_identifier to only the part after the dash: CU533, 117006, 14871, 124512.
- Yellow text lines like "SW:2.4.2" and "SN:21-134" are metadata, not error codes.
- The active error codes are usually small colored rectangles/badges under the "List of errors" heading in the blue panel.
- Badge colors can be red, pink, purple, yellow, orange, white, or low-contrast because of glare.
- Codes in badges can have leading zeroes. Preserve them exactly, for example 002, 003, 007, 011, 022, 029, 113, 240, 250.

Rules:
- Return only codes visible in the image.
- Do not invent meanings or repair steps.
- Keep each visible code as a separate item.
- If the module appears as PLUG_SA, keep that text. The backend will normalize it.
- If two codes are visible, return two errors.
- If three badges are visible, return three errors.
- Ignore normal labels, menu numbers, timestamps, page numbers, controller ids, software versions, serial numbers, and values that are clearly not errors.
- Do not return numbers from SW, SN, or the module header as errors unless the same number is also visible as a colored badge in the List of errors panel.
- If glare, angle, or blur makes one badge uncertain, include it with lower confidence instead of dropping the other readable badges.
PROMPT;
    }

    private function prompt(): string
    {
        return 'Read this Merlo-style dashboard screenshot. Extract the module header, controller identifier, SW, SN, OCR text, and every visible colored error-code badge under the List of errors panel.';
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
            ->where('status', 'active')
            ->whereIn('primary_code_normalized', $this->codeMatchVariants($code));

        if ($moduleKey) {
            $moduleMatch = (clone $baseQuery)
                ->where('module_key', $moduleKey)
                ->orderByDesc('confidence')
                ->first();

            if ($moduleMatch) {
                return [$moduleMatch, 'module_and_code'];
            }

            return [null, 'module_and_code_not_found'];
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
        $cleaned = trim($module);

        if ($cleaned === '') {
            return null;
        }

        $moduleOnly = preg_replace('/\s*[-–—]\s*[A-Z]*\d[A-Z0-9\/._-]*\s*$/iu', '', $cleaned) ?: $cleaned;
        $normalized = Str::upper(preg_replace('/[^A-Z0-9]+/iu', '', trim($moduleOnly)) ?? trim($moduleOnly));

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
