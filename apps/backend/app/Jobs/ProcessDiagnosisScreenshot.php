<?php

namespace App\Jobs;

use App\Models\DiagnosisRequest;
use App\Services\ScreenshotDiagnosticExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessDiagnosisScreenshot implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $diagnosisRequestId,
    ) {
        $this->onQueue('screenshot-diagnosis');
    }

    public int $timeout = 120;

    public int $tries = 1;

    public function handle(ScreenshotDiagnosticExtractionService $extractor): void
    {
        $diagnosis = DiagnosisRequest::query()->findOrFail($this->diagnosisRequestId);

        $diagnosis->update([
            'status' => 'processing',
        ]);

        $extraction = $extractor->extract($diagnosis);
        $candidates = $extractor->storeCandidates($diagnosis, $extraction);
        $matchedCandidates = collect($candidates)->filter(fn ($candidate): bool => $candidate->matched_diagnostic_entry_id !== null);
        $singleResolvedCandidate = count($candidates) === 1 && $matchedCandidates->count() === 1
            ? $matchedCandidates->first()
            : null;

        $diagnosis->update([
            'status' => match (true) {
                $singleResolvedCandidate !== null => 'resolved',
                count($candidates) > 0 => 'needs_confirmation',
                default => 'needs_confirmation',
            },
            'raw_ocr_text' => $extraction['raw_text'],
            'confidence' => collect($extraction['errors'] ?? [])->pluck('confidence')->filter()->avg(),
            'selected_diagnostic_entry_id' => $singleResolvedCandidate?->matched_diagnostic_entry_id,
            'result_payload' => [
                'module_key' => $extraction['module_key'],
                'controller_identifier' => $extraction['controller_identifier'],
                'software_version' => $extraction['software_version'],
                'serial_number' => $extraction['serial_number'],
                'visible_errors_count' => count($extraction['errors'] ?? []),
                'matched_errors_count' => $matchedCandidates->count(),
                'message' => count($candidates) > 0
                    ? 'Visible error codes were extracted from the screenshot.'
                    : 'No visible diagnostic code was found in the screenshot.',
            ],
        ]);
    }

    public function failed(Throwable $throwable): void
    {
        DiagnosisRequest::query()
            ->whereKey($this->diagnosisRequestId)
            ->update([
                'status' => 'failed',
                'result_payload' => [
                    'message' => 'Screenshot extraction failed.',
                    'error' => $throwable->getMessage(),
                ],
            ]);
    }
}
