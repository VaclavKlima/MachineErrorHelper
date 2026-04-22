<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\Manual;
use App\Models\ManualChunk;
use App\Models\ManualExtractionCandidate;
use App\Models\ManualPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class ManualImportService
{
    public function importFromPath(
        Machine $machine,
        string $sourcePath,
        ?string $title = null,
        string $language = 'cs',
        string $coverageMode = 'complete',
        ?string $sourceNotes = null,
    ): Manual {
        $sourcePath = $this->resolvePath($sourcePath);
        $hash = hash_file('sha256', $sourcePath);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'pdf';
        $storedPath = sprintf('manuals/%s/%s.%s', $machine->slug, $hash, $extension);

        Storage::disk('local')->put($storedPath, fopen($sourcePath, 'r'));

        $manual = Manual::updateOrCreate(
            ['file_hash' => $hash],
            [
                'machine_id' => $machine->id,
                'title' => $title ?: pathinfo($sourcePath, PATHINFO_FILENAME),
                'coverage_mode' => $coverageMode,
                'language' => $language,
                'file_path' => $storedPath,
                'page_count' => $this->detectPageCount($sourcePath),
                'source_notes' => $sourceNotes,
                'status' => 'uploaded',
            ],
        );

        return $this->processManual($manual);
    }

    public function processManual(Manual $manual): Manual
    {
        $path = Storage::disk('local')->path($manual->file_path);

        if (! is_file($path)) {
            throw new RuntimeException("Manual file does not exist at [{$path}].");
        }

        $run = $manual->importRuns()->create([
            'status' => 'running',
            'started_at' => now(),
            'extractor_versions' => [
                'pdftotext' => $this->toolVersion('pdftotext'),
                'pdfinfo' => $this->toolVersion('pdfinfo'),
            ],
        ]);

        try {
            $pageCount = $manual->page_count ?: $this->detectPageCount($path);

            DB::transaction(function () use ($manual, $pageCount): void {
                $this->clearPreviousExtraction($manual);

                $manual->forceFill([
                    'page_count' => $pageCount,
                    'status' => 'processing',
                ])->save();
            });

            $chunkIndex = 0;
            $extractedCodes = 0;
            $aiChunks = 0;
            $aiExtractedCodes = 0;

            $currentSectionTitle = null;
            $aiExtractor = app(ManualAiExtractionService::class);
            $maxAiChunks = $aiExtractor->maxChunksPerImport();

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $text = $this->extractPageText($path, $pageNumber);
                $currentSectionTitle = $this->detectSectionTitle($text) ?: $currentSectionTitle;
                $moduleKey = $this->moduleKeyFromSection($currentSectionTitle);

                $page = ManualPage::create([
                    'manual_id' => $manual->id,
                    'page_number' => $pageNumber,
                    'text' => $text,
                    'extraction_quality' => $this->estimateExtractionQuality($text),
                ]);

                foreach ($this->chunkPage($text) as $chunkText) {
                    $chunk = ManualChunk::create([
                        'manual_id' => $manual->id,
                        'manual_page_id' => $page->id,
                        'chunk_index' => $chunkIndex++,
                        'heading' => $this->detectHeading($chunkText),
                        'content' => $chunkText,
                        'content_hash' => hash('sha256', $pageNumber.'|'.$chunkText),
                        'metadata' => [
                            'source' => 'pdftotext',
                            'language' => $manual->language,
                            'section_title' => $currentSectionTitle,
                            'module_key' => $moduleKey,
                        ],
                    ]);

                    $extractedCodes += $this->suggestDiagnosticEntries(
                        $manual,
                        $chunk,
                        $pageNumber,
                        $currentSectionTitle,
                        $moduleKey,
                    );

                    $extractedCodes += $this->suggestTextDiagnosticEntries(
                        $manual,
                        $chunk,
                        $pageNumber,
                        $currentSectionTitle,
                        $moduleKey,
                    );

                    $extractedCodes += $this->suggestDiagnosticBlockEntries(
                        $manual,
                        $chunk,
                        $pageNumber,
                        $currentSectionTitle,
                        $moduleKey,
                    );

                    if (
                        $aiExtractor->enabled()
                        && $aiChunks < $maxAiChunks
                        && $aiExtractor->shouldAnalyze($chunk)
                    ) {
                        $createdByAi = $aiExtractor->extractAndStore(
                            $manual,
                            $chunk,
                            $pageNumber,
                            $currentSectionTitle,
                            $moduleKey,
                        );

                        $extractedCodes += $createdByAi;
                        $aiExtractedCodes += $createdByAi;
                        $aiChunks++;
                    }
                }

                if ($pageNumber % 5 === 0) {
                    $run->forceFill([
                        'stats' => [
                            'pages' => $pageCount,
                            'pages_processed' => $pageNumber,
                            'chunks' => $chunkIndex,
                            'extracted_codes' => $extractedCodes,
                            'ai_chunks' => $aiChunks,
                            'ai_extracted_codes' => $aiExtractedCodes,
                        ],
                    ])->save();
                }
            }

            $manual->forceFill(['status' => 'extracted'])->save();

            $run->forceFill([
                'status' => 'completed',
                'finished_at' => now(),
                'stats' => [
                    'pages' => $pageCount,
                    'pages_processed' => $pageCount,
                    'chunks' => $chunkIndex,
                    'extracted_codes' => $extractedCodes,
                    'ai_chunks' => $aiChunks,
                    'ai_extracted_codes' => $aiExtractedCodes,
                ],
            ])->save();

            return $manual->refresh();
        } catch (\Throwable $throwable) {
            $run->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $throwable->getMessage(),
            ])->save();

            $manual->forceFill(['status' => 'failed'])->save();

            throw $throwable;
        }
    }

    public function resolvePath(string $path): string
    {
        $candidates = [
            $path,
            base_path($path),
            dirname(base_path()).'/'.$path,
            dirname(base_path(), 2).'/'.$path,
            '/var/www/'.$path,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        throw new RuntimeException("Manual PDF was not found at [{$path}].");
    }

    public function detectPageCount(string $path): ?int
    {
        $process = new Process(['pdfinfo', $path]);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        if (preg_match('/^Pages:\s+(?<pages>\d+)/mi', $process->getOutput(), $matches) !== 1) {
            return null;
        }

        return (int) $matches['pages'];
    }

    private function clearPreviousExtraction(Manual $manual): void
    {
        ManualExtractionCandidate::query()
            ->where('manual_id', $manual->id)
            ->whereIn('status', ['pending', 'rejected', 'ignored'])
            ->delete();

        $manual->chunks()->delete();
        $manual->pages()->delete();
    }

    private function extractPageText(string $path, int $pageNumber): string
    {
        $process = new Process(['pdftotext', '-f', (string) $pageNumber, '-l', (string) $pageNumber, '-layout', $path, '-']);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException("pdftotext failed on page {$pageNumber}: ".$process->getErrorOutput());
        }

        return trim($process->getOutput());
    }

    /**
     * Keep early chunks page-local so source references remain clear.
     *
     * @return array<int, string>
     */
    private function chunkPage(string $text): array
    {
        $paragraphs = preg_split("/\n{2,}/u", $text) ?: [];
        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($buffer."\n\n".$paragraph) > 4500 && $buffer !== '') {
                $chunks[] = $buffer;
                $buffer = '';
            }

            $buffer = trim($buffer."\n\n".$paragraph);
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks ?: [$text];
    }

    private function suggestDiagnosticEntries(
        Manual $manual,
        ManualChunk $chunk,
        int $pageNumber,
        ?string $sectionTitle,
        ?string $moduleKey,
    ): int {
        $created = 0;
        $context = array_filter([
            'module' => $moduleKey,
            'section_title' => $sectionTitle,
            'manual_language' => $manual->language,
            'coverage_mode' => $manual->coverage_mode,
        ]);

        foreach ($this->extractCandidateBlocks($chunk->content) as $block) {
            $code = $this->detectPrimaryCode($block);

            if ($code === null) {
                continue;
            }

            $normalizedCode = $this->normalizeCode($code);
            [$meaning, $recommendedAction] = $this->extractMeaningAndAction($block, $code, $moduleKey);

            if ($meaning === '' || $this->isLayoutNoise($meaning)) {
                continue;
            }

            if ($this->looksLikeTableOfContentsRow($block, $meaning)) {
                continue;
            }

            $identifiers = $this->inferIdentifiers($block, $normalizedCode, $sectionTitle);
            $candidateType = str_contains($moduleKey ?? '', 'DTCJ1939') || isset($identifiers['spn'])
                ? 'diagnostic_entry_j1939'
                : 'diagnostic_entry';

            $candidate = [
                'machine_id' => $manual->machine_id,
                'manual_id' => $manual->id,
                'manual_page_id' => $chunk->manual_page_id,
                'manual_chunk_id' => $chunk->id,
                'candidate_type' => $candidateType,
                'code' => $code,
                'normalized_code' => $normalizedCode,
                'family' => $moduleKey,
                'module_key' => $moduleKey,
                'section_title' => $sectionTitle,
                'primary_code' => $code,
                'context' => $context,
                'identifiers' => $identifiers,
                'title' => Str::limit($meaning, 250, ''),
                'meaning' => $meaning,
                'recommended_action' => $recommendedAction !== '' ? $recommendedAction : null,
                'source_text' => $block,
                'source_page_number' => $pageNumber,
                'extractor' => 'generic_section_table',
                'confidence' => $this->scoreCandidate($normalizedCode, $meaning, $recommendedAction, $context, $identifiers),
                'metadata' => [
                    'table_shape' => $candidateType,
                    'chunk_heading' => $chunk->heading,
                    'section_title' => $sectionTitle,
                ],
            ];

            $this->storeExtractedCandidate($candidate);

            $created++;
        }

        return $created;
    }

    private function suggestDiagnosticBlockEntries(
        Manual $manual,
        ManualChunk $chunk,
        int $pageNumber,
        ?string $sectionTitle,
        ?string $moduleKey,
    ): int {
        $created = 0;
        $context = array_filter([
            'module' => $moduleKey,
            'section_title' => $sectionTitle,
            'manual_language' => $manual->language,
            'coverage_mode' => $manual->coverage_mode,
        ]);

        foreach ($this->extractDiagnosticBlocks($chunk->content) as $block) {
            $primaryCode = $this->normalizeNumericIdentifier($block['error_number']);
            $normalizedCode = $this->normalizeCode($primaryCode);
            $fields = $block['fields'];
            $meaning = $this->cleanExtractedSentence($fields['fault'] ?? '');
            $cause = $this->cleanExtractedSentence($fields['cause'] ?? '');
            $recommendedAction = $this->cleanExtractedSentence($fields['action'] ?? '');
            $title = $this->cleanExtractedSentence($block['title'] ?: $meaning);

            if ($meaning === '' || $this->isLayoutNoise($meaning)) {
                continue;
            }

            $identifiers = array_filter([
                'code' => $normalizedCode,
                'error_number' => $normalizedCode,
                'indicator_code' => isset($block['indicator_code']) ? $this->normalizeCode($block['indicator_code']) : null,
            ]);

            $candidateContext = array_filter(array_merge($context, [
                'indicator_code' => $identifiers['indicator_code'] ?? null,
                'neutral_effect' => $this->cleanExtractedSentence($fields['neutral_effect'] ?? ''),
                'driving_effect' => $this->cleanExtractedSentence($fields['driving_effect'] ?? ''),
                'reset_condition' => $this->cleanExtractedSentence($fields['reset_condition'] ?? ''),
            ]));

            $candidate = [
                'machine_id' => $manual->machine_id,
                'manual_id' => $manual->id,
                'manual_page_id' => $chunk->manual_page_id,
                'manual_chunk_id' => $chunk->id,
                'candidate_type' => 'diagnostic_entry_block',
                'code' => $primaryCode,
                'normalized_code' => $normalizedCode,
                'family' => $moduleKey,
                'module_key' => $moduleKey,
                'section_title' => $sectionTitle,
                'primary_code' => $primaryCode,
                'context' => $candidateContext,
                'identifiers' => $identifiers,
                'title' => Str::limit($title, 250, ''),
                'meaning' => $meaning,
                'cause' => $cause !== '' ? $cause : null,
                'recommended_action' => $recommendedAction !== '' ? $recommendedAction : null,
                'source_text' => $block['source_text'],
                'source_page_number' => $pageNumber,
                'extractor' => 'generic_diagnostic_block',
                'confidence' => $this->scoreDiagnosticBlockCandidate($normalizedCode, $meaning, $recommendedAction, $candidateContext, $identifiers),
                'metadata' => [
                    'table_shape' => 'single_error_block',
                    'chunk_heading' => $chunk->heading,
                    'section_title' => $sectionTitle,
                    'parsed_fields' => array_filter($fields),
                ],
            ];

            $this->storeExtractedCandidate($candidate);

            $created++;
        }

        return $created;
    }

    private function suggestTextDiagnosticEntries(
        Manual $manual,
        ManualChunk $chunk,
        int $pageNumber,
        ?string $sectionTitle,
        ?string $moduleKey,
    ): int {
        $created = 0;
        $context = array_filter([
            'module' => $moduleKey,
            'section_title' => $sectionTitle,
            'manual_language' => $manual->language,
            'coverage_mode' => $manual->coverage_mode,
        ]);

        foreach ($this->extractDiagnosticParagraphs($chunk->content) as $paragraph) {
            $identifiers = $this->inferTextIdentifiers($paragraph);
            $primaryCode = $this->primaryCodeFromIdentifiers($identifiers);

            if ($primaryCode === null) {
                continue;
            }

            [$meaning, $recommendedAction] = $this->extractTextMeaningAndAction($paragraph, $primaryCode, $moduleKey);

            if ($meaning === '' || $this->isLayoutNoise($meaning)) {
                continue;
            }

            $normalizedCode = $this->normalizeCode($primaryCode);
            $candidateType = isset($identifiers['spn']) || isset($identifiers['fmi'])
                ? 'diagnostic_entry_text_j1939'
                : 'diagnostic_entry_text';

            $candidate = [
                'machine_id' => $manual->machine_id,
                'manual_id' => $manual->id,
                'manual_page_id' => $chunk->manual_page_id,
                'manual_chunk_id' => $chunk->id,
                'candidate_type' => $candidateType,
                'code' => $primaryCode,
                'normalized_code' => $normalizedCode,
                'family' => $moduleKey,
                'module_key' => $moduleKey,
                'section_title' => $sectionTitle,
                'primary_code' => $primaryCode,
                'context' => $context,
                'identifiers' => $identifiers,
                'title' => Str::limit($meaning, 250, ''),
                'meaning' => $meaning,
                'recommended_action' => $recommendedAction !== '' ? $recommendedAction : null,
                'source_text' => $paragraph,
                'source_page_number' => $pageNumber,
                'extractor' => 'generic_text_reference',
                'confidence' => $this->scoreTextCandidate($normalizedCode, $meaning, $recommendedAction, $context, $identifiers),
                'metadata' => [
                    'table_shape' => $candidateType,
                    'chunk_heading' => $chunk->heading,
                    'section_title' => $sectionTitle,
                ],
            ];

            $this->storeExtractedCandidate($candidate);

            $created++;
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function withReviewClassification(array $candidate): array
    {
        return array_merge($candidate, app(ManualExtractionCandidateReviewClassifier::class)->classify($candidate));
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function storeExtractedCandidate(array $candidate): ManualExtractionCandidate
    {
        $storedCandidate = ManualExtractionCandidate::create($this->withReviewClassification($candidate));

        app(ManualExtractionCandidatePublishingService::class)->publish($storedCandidate);

        return $storedCandidate;
    }

    /**
     * @return array<int, string>
     */
    private function extractDiagnosticParagraphs(string $content): array
    {
        $content = str_replace("\f", "\n\n", $content);
        $paragraphs = preg_split("/\n\s*\n/u", $content) ?: [];
        $candidates = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = $this->cleanExtractedSentence($paragraph);

            if ($paragraph === '' || $this->isLayoutNoise($paragraph)) {
                continue;
            }

            if ($this->blockContainsCode($paragraph)) {
                continue;
            }

            if (! $this->containsDiagnosticTextReference($paragraph)) {
                continue;
            }

            $candidates[] = $paragraph;
        }

        return $candidates;
    }

    /**
     * Manuals sometimes describe one diagnostic code per mini-table instead of
     * using a single table with many codes. Example markers:
     * "ČÍSLO CHYBY 12" and "KÓD CHYBY [ŽLUTÁ LED] 001100 Engine can - bus off".
     *
     * @return array<int, array{
     *     error_number: string,
     *     indicator_code?: string,
     *     title: string,
     *     fields: array<string, string>,
     *     source_text: string
     * }>
     */
    private function extractDiagnosticBlocks(string $content): array
    {
        $lines = preg_split('/\R/u', str_replace("\f", "\n", $content)) ?: [];
        $blocks = [];
        $current = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($current !== null) {
                    $current['lines'][] = '';
                }

                continue;
            }

            if (preg_match('/\b(?:ČÍSLO|CISLO)\s+CHYBY\s+(?<code>\d{1,6})\b/iu', $trimmed, $matches) === 1) {
                if ($current !== null) {
                    $blocks[] = $this->parseDiagnosticBlock($current);
                }

                $current = [
                    'error_number' => $matches['code'],
                    'lines' => [$line],
                ];

                continue;
            }

            if ($current !== null) {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $blocks[] = $this->parseDiagnosticBlock($current);
        }

        return array_values(array_filter($blocks, fn (?array $block): bool => $block !== null));
    }

    /**
     * @param  array{error_number: string, lines: array<int, string>}  $block
     * @return array{
     *     error_number: string,
     *     indicator_code?: string,
     *     title: string,
     *     fields: array<string, string>,
     *     source_text: string
     * }|null
     */
    private function parseDiagnosticBlock(array $block): ?array
    {
        $fields = [];
        $currentField = null;
        $indicatorCode = null;
        $title = '';
        $sourceLines = [];

        foreach ($block['lines'] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || $this->isLayoutNoise($trimmed) || $this->isWatermarkLine($trimmed, null)) {
                continue;
            }

            $sourceLines[] = $line;

            if (preg_match('/\bK[ÓO]D\s+CHYBY\b.*?\s(?<indicator>(?:0x[0-9A-F]+|[01]{3,}|\d{3,}))\s+(?<title>.+)$/iu', $trimmed, $matches) === 1) {
                $indicatorCode = $matches['indicator'];
                $title = $this->cleanExtractedSentence($matches['title']);
                $currentField = null;

                continue;
            }

            if (preg_match('/\b(?:ČÍSLO|CISLO)\s+CHYBY\b/iu', $trimmed) === 1) {
                $currentField = null;

                continue;
            }

            [$field, $value] = $this->extractDiagnosticBlockField($trimmed);

            if ($field !== null) {
                $currentField = $field;

                if ($value !== '') {
                    $fields[$field] = trim(($fields[$field] ?? '').' '.$value);
                }

                continue;
            }

            if ($currentField !== null) {
                $fields[$currentField] = trim(($fields[$currentField] ?? '').' '.$trimmed);
            }
        }

        if (($fields['fault'] ?? '') === '') {
            return null;
        }

        $result = [
            'error_number' => $block['error_number'],
            'indicator_code' => $indicatorCode,
            'title' => $title,
            'fields' => array_map(fn (string $value): string => $this->cleanExtractedSentence($value), $fields),
            'source_text' => trim(implode("\n", $sourceLines)),
        ];

        if ($result['indicator_code'] === null || $result['indicator_code'] === '') {
            unset($result['indicator_code']);
        }

        return $result;
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function extractDiagnosticBlockField(string $line): array
    {
        $labels = [
            'fault' => 'Zjištěná\s+závada',
            'cause' => 'Pravděpodobná\s+příčina',
            'neutral_effect' => 'Hydrostatický\s+náhon\s*:\s*V\s+neutrálu',
            'driving_effect' => 'Hydrostatický\s+náhon\s*:\s*Za\s+jízdy',
            'reset_condition' => 'Ukončení\s+signalizace',
            'action' => 'Možné\s+řešení',
        ];

        foreach ($labels as $field => $label) {
            if (preg_match('/^'.$label.'(?:\s+(?<value>.*))?$/iu', $line, $matches) === 1) {
                return [$field, trim($matches['value'] ?? '')];
            }
        }

        return [null, ''];
    }

    private function containsDiagnosticTextReference(string $text): bool
    {
        return preg_match('/\b(?:chyb(?:a|u|y|ový|ového|ove|ové)?(?:\s+k[oó]d)?|k[oó]d|error|fault|dtc)\b\s*(?:č\.|c\.|no\.|number|#|:)?\s*(?:0x[0-9A-F]{2,}|[A-Z]{0,8}[- ]?\d{1,6})\b/iu', $text) === 1
            || preg_match('/\bSPN\s*:?\s*\d{1,6}\b.+\bFMI\s*:?\s*(?:\d{1,2}|31)\b/iu', $text) === 1;
    }

    /**
     * @return array<string, string>
     */
    private function inferTextIdentifiers(string $text): array
    {
        $identifiers = [];

        if (preg_match('/\b(?:chyb(?:a|u|y|ový|ového|ove|ové)?(?:\s+k[oó]d)?|k[oó]d|error|fault|dtc)\b\s*(?:č\.|c\.|no\.|number|#|:)?\s*(?<code>0x[0-9A-F]{2,}|[A-Z]{0,8}[- ]?\d{1,6})\b/iu', $text, $matches) === 1) {
            $identifiers['code'] = $this->normalizeCode($matches['code']);
        }

        if (preg_match('/\bSPN\s*:?\s*(?<spn>\d{1,6})\b/iu', $text, $matches) === 1) {
            $identifiers['spn'] = $this->normalizeNumericIdentifier($matches['spn']);
        }

        if (preg_match('/\bFMI\s*:?\s*(?<fmi>\d{1,2}|31)\b/iu', $text, $matches) === 1) {
            $identifiers['fmi'] = $matches['fmi'];
        }

        if (preg_match('/\bSAD\s*(?:\(MerloTool\))?\s*:?\s*(?<sad>0x[0-9A-F]{2,}|\d{1,3})\b/iu', $text, $matches) === 1) {
            $key = str_starts_with(Str::lower($matches['sad']), '0x') ? 'sad_merlotool' : 'sad_mps';
            $identifiers[$key] = Str::upper($matches['sad']);
        }

        if (! isset($identifiers['code']) && isset($identifiers['spn'])) {
            $identifiers['code'] = $identifiers['spn'];
        }

        return $identifiers;
    }

    /**
     * @param  array<string, string>  $identifiers
     */
    private function primaryCodeFromIdentifiers(array $identifiers): ?string
    {
        return $identifiers['code'] ?? $identifiers['spn'] ?? null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractTextMeaningAndAction(string $paragraph, string $primaryCode, ?string $moduleKey): array
    {
        $sentences = preg_split('/(?<=[.!?。])\s+/u', $paragraph) ?: [$paragraph];
        $meaning = [];
        $action = [];

        foreach ($sentences as $sentence) {
            $sentence = $this->cleanExtractedSentence($sentence);

            if ($sentence === '' || $this->isWatermarkLine($sentence, $moduleKey)) {
                continue;
            }

            if ($this->looksLikeActionLine($sentence, $sentence)) {
                $action[] = $sentence;

                continue;
            }

            $meaning[] = $sentence;
        }

        $meaningText = $this->cleanExtractedSentence(implode(' ', $meaning));
        $actionText = $this->cleanExtractedSentence(implode(' ', $action));

        if ($meaningText === '') {
            $meaningText = $this->cleanExtractedSentence($paragraph);
        }

        $meaningText = preg_replace('/^\s*(?:chyb(?:a|u|y|ový|ového|ove|ové)?(?:\s+k[oó]d)?|k[oó]d|error|fault|dtc)\b\s*(?:č\.|c\.|no\.|number|#|:)?\s*'.preg_quote($primaryCode, '/').'\s*(?:znamen[aá]|means|indicates|signalizuje)?\s*[:,\-–]?\s*/iu', '', $meaningText) ?? $meaningText;

        if (preg_match('/\b(?:chyb(?:a|u|y|ový|ového|ove|ové)?|k[oó]d|error|fault|dtc)\b.+\b(?:znamen[aá]|means|indicates|signalizuje)\b/iu', $meaningText) === 1) {
            $meaningText = preg_replace('/^.*?\b(?:znamen[aá]|means|indicates|signalizuje)\b\s*[:,\-–]?\s*/iu', '', $meaningText) ?? $meaningText;
        }

        return [
            $this->cleanExtractedSentence($meaningText),
            $actionText,
        ];
    }

    /**
     * PDF text extraction often moves the code column into the middle of a row.
     * Blank-line table blocks preserve enough nearby evidence to reconstruct a
     * generic diagnostic candidate without knowing the table schema upfront.
     *
     * @return array<int, string>
     */
    private function extractCandidateBlocks(string $content): array
    {
        $content = str_replace("\f", "\n\n", $content);
        $blocks = preg_split("/\n\s*\n/u", $content) ?: [];
        $candidates = [];

        for ($index = 0; $index < count($blocks); $index++) {
            $block = $blocks[$index];
            $block = trim($block);

            if ($block === '' || $this->isLayoutNoise($block)) {
                continue;
            }

            if (! $this->blockContainsCode($block)) {
                continue;
            }

            $nextBlock = trim($blocks[$index + 1] ?? '');

            if ($this->codeLineHasNoDescription($block) && $nextBlock !== '' && ! $this->blockContainsCode($nextBlock)) {
                $block = trim($block."\n".$nextBlock);
                $index++;
            }

            $candidates[] = $block;
        }

        return $candidates;
    }

    private function blockContainsCode(string $block): bool
    {
        return preg_match('/^\s*(?:0x[0-9A-F]{2,}|\d{1,6}|[A-Z]{1,8}[- ]?\d{1,6})\b/miu', $block) === 1;
    }

    private function codeLineHasNoDescription(string $block): bool
    {
        foreach (preg_split('/\R/u', $block) ?: [] as $line) {
            $line = trim($line);

            if (preg_match('/^(?:0x[0-9A-F]{2,}|\d{1,6}|[A-Z]{1,8}[- ]?\d{1,6})\b(?<rest>.*)$/iu', $line, $matches) === 1) {
                return trim($matches['rest']) === '';
            }
        }

        return false;
    }

    private function detectPrimaryCode(string $block): ?string
    {
        foreach (preg_split('/\R/u', $block) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^0x[0-9A-F]{2,}.+?\b(?<spn>\d{3,6})\s+(?<fmi>\d{1,2}|31)\b/iu', $line, $matches) === 1) {
                return $matches['spn'];
            }

            if (preg_match('/^(?<code>[A-Z]{1,8}[- ]?\d{1,6}|\d{1,6})\b/u', $line, $matches) === 1) {
                return $matches['code'];
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractMeaningAndAction(string $block, string $code, ?string $moduleKey): array
    {
        $meaning = [];
        $action = [];

        foreach (preg_split('/\R/u', $block) ?: [] as $line) {
            $line = rtrim($line);
            $trimmed = trim($line);

            if ($trimmed === '' || $this->isLayoutNoise($trimmed) || $this->isWatermarkLine($trimmed, $moduleKey)) {
                continue;
            }

            $withoutCode = trim(preg_replace('/^'.preg_quote($code, '/').'\b/u', '', $trimmed) ?? $trimmed);

            if ($withoutCode === '') {
                continue;
            }

            if ($this->looksLikeActionLine($line, $withoutCode)) {
                $action[] = $withoutCode;

                continue;
            }

            $meaning[] = $withoutCode;
        }

        return [
            $this->cleanExtractedSentence(implode(' ', $meaning)),
            $this->cleanExtractedSentence(implode(' ', $action)),
        ];
    }

    private function looksLikeActionLine(string $rawLine, string $text): bool
    {
        $leadingSpaces = strlen($rawLine) - strlen(ltrim($rawLine));

        if ($leadingSpaces >= 45) {
            return true;
        }

        return preg_match('/^(Zkontrolujte|Vyměňte|Vymente|Obraťte|Obratte|Zkalibrujte|Zopakujte|Prosím|Prosim)\b/ui', $text) === 1;
    }

    private function cleanExtractedSentence(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        $text = str_replace([' ne- shodují', ' ne- shoduje'], [' neshodují', ' neshoduje'], $text);

        return trim($text);
    }

    /**
     * @return array<string, string>
     */
    private function inferIdentifiers(string $block, string $normalizedCode, ?string $sectionTitle): array
    {
        $identifiers = ['code' => $normalizedCode];
        $flat = preg_replace('/\s+/u', ' ', $block) ?? $block;
        $isJ1939 = str_contains(Str::upper($sectionTitle ?? ''), 'J1939')
            || preg_match('/\b(?:SPN|FMI|SAD)\b/ui', $block) === 1;

        if (! $isJ1939) {
            return $identifiers;
        }

        if (preg_match('/\b(?<sad>0x[0-9A-F]{2,})\b/iu', $flat, $matches) === 1) {
            $identifiers['sad_merlotool'] = Str::upper($matches['sad']);
        }

        if (preg_match('/\b0x[0-9A-F]{2,}[^\d]+(?<sad_mps>\d{1,3})\s+(?<spn>\d{3,6})\s+(?<fmi>\d{1,2}|31)\b/iu', $flat, $matches) === 1) {
            $identifiers['sad_mps'] = $matches['sad_mps'];
            $identifiers['spn'] = $this->normalizeNumericIdentifier($matches['spn']);
            $identifiers['fmi'] = $matches['fmi'];

            return $identifiers;
        }

        if (preg_match('/\b(?<spn>\d{3,6})\s+(?<fmi>\d{1,2}|31)\b/u', $flat, $matches) === 1) {
            $identifiers['spn'] = $this->normalizeNumericIdentifier($matches['spn']);
            $identifiers['fmi'] = $matches['fmi'];

            return $identifiers;
        }

        if (preg_match('/\b(?<spn>\d{3,6})\b/u', $flat, $matches) === 1) {
            $identifiers['spn'] = $this->normalizeNumericIdentifier($matches['spn']);
        }

        return $identifiers;
    }

    private function normalizeNumericIdentifier(string $value): string
    {
        $trimmed = ltrim($value, '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    private function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/[\s-]+/u', '', trim($code)) ?? trim($code));
    }

    private function isLayoutNoise(string $text): bool
    {
        return str_contains($text, 'DIAGNOSTIKA')
            || str_contains($text, 'CHYBOVÉ KÓDY')
            || str_contains($text, 'MADE IN ITALY')
            || str_contains($text, 'MOŽNÉ ŘEŠENÍ')
            || str_contains($text, 'MOŽNÁ NÁPRAVA')
            || preg_match('/^\d+\s*$/', $text) === 1;
    }

    private function looksLikeTableOfContentsRow(string $line, string $title): bool
    {
        return str_contains($line, '...')
            || preg_match('/\.{3,}\s*\d+\s*$/u', $line) === 1
            || preg_match('/\.{3,}\s*\d+\s*$/u', $title) === 1;
    }

    private function isWatermarkLine(string $text, ?string $moduleKey): bool
    {
        $normalized = $this->normalizeModuleKey($text);

        if ($moduleKey !== null && $normalized !== '' && str_contains($moduleKey, $normalized)) {
            return true;
        }

        return preg_match('/^[A-Z](?:\s+[A-Z])+$/u', $text) === 1
            || preg_match('/^[A-Z]{1,3}$/u', $text) === 1;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $identifiers
     */
    private function scoreCandidate(string $code, string $title, string $recommendedAction, array $context = [], array $identifiers = []): float
    {
        $score = 0.45;

        if (preg_match('/^\d{1,6}$/', $code) === 1) {
            $score += 0.05;
        }

        if (preg_match('/^[A-Z]{1,8}\d{1,6}$/', $code) === 1) {
            $score += 0.15;
        }

        if (mb_strlen($title) >= 8) {
            $score += 0.1;
        }

        if ($recommendedAction !== '') {
            $score += 0.15;
        }

        if (($context['module'] ?? null) !== null) {
            $score += 0.1;
        }

        if (count($identifiers) > 1) {
            $score += 0.1;
        }

        return round(min($score, 0.95), 4);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $identifiers
     */
    private function scoreTextCandidate(string $code, string $title, string $recommendedAction, array $context = [], array $identifiers = []): float
    {
        return round(max($this->scoreCandidate($code, $title, $recommendedAction, $context, $identifiers) - 0.18, 0.35), 4);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $identifiers
     */
    private function scoreDiagnosticBlockCandidate(string $code, string $title, string $recommendedAction, array $context = [], array $identifiers = []): float
    {
        return round(min($this->scoreCandidate($code, $title, $recommendedAction, $context, $identifiers) + 0.12, 0.98), 4);
    }

    private function detectSectionTitle(string $text): ?string
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $leadingSpaces = strlen($line) - strlen(ltrim($line));
            $line = $this->cleanExtractedSentence($line);

            if ($line === '' || $this->isLayoutNoise($line)) {
                continue;
            }

            if ($leadingSpaces >= 20 && preg_match('/^DTC\s+J1939(?:\s*\([^)]+\))?(?:\s+[-–]\s+.+)?$/iu', $line) === 1) {
                return $line;
            }

            if ($leadingSpaces >= 20 && preg_match('/^[A-Z0-9]{2,12}(?:\/[A-Z0-9]{2,12})?\s+[-–]\s+.+$/u', $line) === 1) {
                return $line;
            }
        }

        return null;
    }

    private function moduleKeyFromSection(?string $sectionTitle): ?string
    {
        if ($sectionTitle === null) {
            return null;
        }

        if (preg_match('/^DTC\s+J1939/iu', $sectionTitle) === 1) {
            return 'DTCJ1939';
        }

        $module = trim(Str::before($sectionTitle, '-'));

        return $this->normalizeModuleKey($module);
    }

    private function normalizeModuleKey(string $module): string
    {
        return Str::upper(preg_replace('/[^A-Z0-9]+/iu', '', $module) ?? $module);
    }

    private function detectHeading(string $text): ?string
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || $this->isLayoutNoise($line)) {
                continue;
            }

            if (preg_match('/^[A-Z0-9]{2,12}\s+-\s+.+$/u', $line) === 1) {
                return Str::limit($line, 120, '');
            }
        }

        return null;
    }

    private function estimateExtractionQuality(string $text): float
    {
        if ($text === '') {
            return 0.0;
        }

        $letters = preg_match_all('/[\pL\pN]/u', $text);
        $length = max(mb_strlen($text), 1);

        return round(min($letters / $length, 1), 4);
    }

    private function toolVersion(string $binary): ?string
    {
        $process = new Process([$binary, '-v']);
        $process->run();

        return trim($process->getOutput() ?: $process->getErrorOutput()) ?: null;
    }
}
