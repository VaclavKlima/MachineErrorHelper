<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDiagnosisScreenshot;
use App\Models\DiagnosisRequest;
use App\Models\DiagnosticEntry;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiagnosisController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id' => ['required', 'integer', 'exists:machines,id'],
            'software_version_id' => ['nullable', 'integer', 'exists:software_versions,id'],
            'screenshot' => ['required', 'image', 'max:20480'],
        ]);

        $machine = Machine::query()
            ->where('is_active', true)
            ->findOrFail($data['machine_id']);

        $path = $request->file('screenshot')->store('diagnosis-screenshots');

        $diagnosis = DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'software_version_id' => $data['software_version_id'] ?? null,
            'screenshot_path' => $path,
            'status' => 'uploaded',
        ]);

        ProcessDiagnosisScreenshot::dispatch($diagnosis->id);

        return response()->json([
            'id' => $diagnosis->public_id,
            'status' => $diagnosis->status,
            'poll_url' => route('api.diagnoses.show', $diagnosis),
        ], 201);
    }

    public function show(DiagnosisRequest $diagnosis): JsonResponse
    {
        $diagnosis->load([
            'machine:id,name,slug,manufacturer,model_number',
            'softwareVersion:id,version',
            'selectedDiagnosticEntry',
            'candidates.matchedDiagnosticEntry',
        ]);

        return response()->json([
            'data' => [
                'id' => $diagnosis->public_id,
                'status' => $diagnosis->status,
                'machine' => $diagnosis->machine,
                'software_version' => $diagnosis->softwareVersion,
                'confidence' => $diagnosis->confidence,
                'candidates' => $diagnosis->candidates,
                'selected_diagnostic_entry' => $diagnosis->selectedDiagnosticEntry,
                'result' => $diagnosis->result_payload,
                'screenshot_url' => $diagnosis->screenshot_path
                    ? Storage::url($diagnosis->screenshot_path)
                    : null,
            ],
        ]);
    }

    public function confirmCode(Request $request, DiagnosisRequest $diagnosis): JsonResponse
    {
        $data = $request->validate([
            'candidate_id' => ['required', 'integer', 'exists:diagnosis_candidates,id'],
        ]);

        $candidate = $diagnosis->candidates()->findOrFail($data['candidate_id']);

        $diagnosis->update([
            'selected_error_code_id' => $candidate->matched_error_code_id,
            'selected_definition_id' => $candidate->matched_definition_id,
            'selected_diagnostic_entry_id' => $candidate->matched_diagnostic_entry_id,
            'status' => ($candidate->matched_definition_id || $candidate->matched_diagnostic_entry_id) ? 'resolved' : 'needs_confirmation',
        ]);

        return $this->show($diagnosis->refresh());
    }

    public function manualCode(Request $request, DiagnosisRequest $diagnosis): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:255', 'required_without:codes'],
            'codes' => ['nullable', 'array', 'required_without:code'],
            'codes.*' => ['string', 'max:64'],
            'module_key' => ['nullable', 'string', 'max:64'],
        ]);

        $codes = $this->extractManualCodes($data);
        $moduleKey = $this->normalizeModuleKey((string) ($data['module_key'] ?? ''));
        $matchedCandidates = 0;
        $candidateConfidences = [];

        $diagnosis->candidates()->delete();

        foreach ($codes as $code) {
            $normalizedCode = $this->normalizeDiagnosticCode($code);

            if ($normalizedCode === '') {
                continue;
            }

            [$match, $matchingStrategy] = $this->matchDiagnosticEntry($diagnosis, $normalizedCode, $moduleKey);

            if ($match) {
                $matchedCandidates++;
            }

            $confidence = $match ? 1.0 : 0.6;
            $candidateConfidences[] = $confidence;

            $diagnosis->candidates()->create([
                'candidate_code' => $code,
                'normalized_code' => $normalizedCode,
                'source' => 'manual_entry',
                'confidence' => $confidence,
                'matched_diagnostic_entry_id' => $match?->id,
                'metadata' => array_filter([
                    'module_key' => $moduleKey,
                    'matching_strategy' => $matchingStrategy,
                ]),
            ]);
        }

        $singleResolvedCode = count($codes) === 1 && $matchedCandidates === 1
            ? $diagnosis->candidates()->whereNotNull('matched_diagnostic_entry_id')->first()
            : null;

        $diagnosis->update([
            'selected_diagnostic_entry_id' => $singleResolvedCode?->matched_diagnostic_entry_id,
            'status' => $singleResolvedCode ? 'resolved' : 'needs_confirmation',
            'confidence' => count($candidateConfidences) > 0
                ? round(array_sum($candidateConfidences) / count($candidateConfidences), 4)
                : null,
            'result_payload' => array_filter([
                'module_key' => $moduleKey,
                'visible_errors_count' => count($codes),
                'matched_errors_count' => $matchedCandidates,
                'source' => 'manual_entry',
                'message' => count($codes) > 1
                    ? 'Multiple manual error codes were entered for this module.'
                    : 'Manual error code was entered.',
            ], fn ($value): bool => $value !== null && $value !== ''),
        ]);

        return $this->show($diagnosis->refresh());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function extractManualCodes(array $data): array
    {
        $values = [];

        if (is_array($data['codes'] ?? null)) {
            $values = $data['codes'];
        } elseif (isset($data['code'])) {
            $values = [$data['code']];
        }

        $codes = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            foreach (preg_split('/[\s,;|]+/u', (string) $value) ?: [] as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $codes[] = $part;
                }
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @return array{0: DiagnosticEntry|null, 1: string}
     */
    private function matchDiagnosticEntry(DiagnosisRequest $diagnosis, string $normalizedCode, ?string $moduleKey): array
    {
        $baseQuery = DiagnosticEntry::query()
            ->where('machine_id', $diagnosis->machine_id)
            ->where('status', 'active')
            ->whereIn('primary_code_normalized', $this->codeMatchVariants($normalizedCode));

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

    private function normalizeDiagnosticCode(string $code): string
    {
        return Str::upper(preg_replace('/\s+/', '', trim($code)) ?? trim($code));
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
}
