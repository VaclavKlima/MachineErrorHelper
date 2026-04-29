<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDiagnosisScreenshot;
use App\Models\DashboardColorMeaning;
use App\Models\DiagnosisRequest;
use App\Models\DiagnosticEntry;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiagnosisController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        $diagnoses = DiagnosisRequest::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'machine:id,name,slug,manufacturer,model_number',
                'selectedDiagnosticEntry:id,machine_id,module_key,primary_code,title',
                'candidates' => fn ($query) => $query
                    ->select('id', 'diagnosis_request_id', 'candidate_code', 'normalized_code', 'source', 'confidence', 'dashboard_color_meaning_id', 'metadata')
                    ->with('dashboardColorMeaning:id,label,ai_key,hex_color,description,priority'),
            ])
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $diagnoses->map(fn (DiagnosisRequest $diagnosis): array => [
                'id' => $diagnosis->public_id,
                'status' => $diagnosis->status,
                'created_at' => $diagnosis->created_at?->toJSON(),
                'confidence' => $diagnosis->confidence,
                'machine' => $diagnosis->machine,
                'selected_diagnostic_entry' => $diagnosis->selectedDiagnosticEntry,
                'ai_detected_codes' => $diagnosis->ai_detected_codes ?? [],
                'user_entered_codes' => $diagnosis->user_entered_codes ?? [],
                'candidates' => $diagnosis->candidates,
                'screenshot_url' => $diagnosis->screenshot_url,
            ])->values()->all(),
        ]);
    }

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
            'user_id' => $request->user()?->id,
            'software_version_id' => $data['software_version_id'] ?? null,
            'screenshot_path' => $path,
            'status' => 'uploaded',
            'ai_detected_codes' => [],
            'user_entered_codes' => [],
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
            'machine' => fn ($query) => $query
                ->select('id', 'name', 'slug', 'manufacturer', 'model_number')
                ->with(['dashboardColorMeanings' => fn ($query) => $query->where('is_active', true)->orderBy('priority')->orderBy('label')]),
            'softwareVersion:id,version',
            'selectedDiagnosticEntry.codeDocumentations',
            'candidates' => fn ($query) => $query
                ->with([
                    'matchedDiagnosticEntry.codeDocumentations',
                    'dashboardColorMeaning:id,label,ai_key,hex_color,description,priority',
                ]),
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
                'screenshot_url' => $diagnosis->screenshot_url,
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
            'code' => ['nullable', 'string', 'max:255', 'required_without_all:codes,entries'],
            'codes' => ['nullable', 'array', 'required_without_all:code,entries'],
            'codes.*' => ['string', 'max:64'],
            'entries' => ['nullable', 'array', 'required_without_all:code,codes'],
            'entries.*.code' => ['required_with:entries', 'string', 'max:64'],
            'entries.*.dashboard_color_meaning_id' => ['nullable', 'integer', 'exists:dashboard_color_meanings,id'],
            'module_key' => ['nullable', 'string', 'max:64'],
        ]);

        $entries = $this->extractManualEntries($data);
        $codes = array_values(array_unique(array_map(fn (array $entry): string => $entry['code'], $entries)));
        $moduleKey = $this->normalizeModuleKey((string) ($data['module_key'] ?? ''));
        $matchedCandidates = 0;
        $candidateConfidences = [];
        $colorMeanings = DashboardColorMeaning::query()
            ->where('machine_id', $diagnosis->machine_id)
            ->whereIn('id', array_filter(array_map(fn (array $entry): ?int => $entry['dashboard_color_meaning_id'], $entries)))
            ->get()
            ->keyBy('id');

        $diagnosis->candidates()->delete();

        foreach ($entries as $entry) {
            $code = $entry['code'];
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
            $colorMeaning = $entry['dashboard_color_meaning_id']
                ? $colorMeanings->get($entry['dashboard_color_meaning_id'])
                : null;

            $diagnosis->candidates()->create([
                'candidate_code' => $code,
                'normalized_code' => $normalizedCode,
                'source' => 'manual_entry',
                'confidence' => $confidence,
                'matched_diagnostic_entry_id' => $match?->id,
                'dashboard_color_meaning_id' => $colorMeaning?->id,
                'metadata' => array_filter([
                    'module_key' => $moduleKey,
                    'color_status_key' => $colorMeaning?->ai_key,
                    'color_status_label' => $colorMeaning?->label,
                    'color_status_description' => $colorMeaning?->description,
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
            'user_entered_codes' => $codes,
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
     * @return array<int, array{code: string, dashboard_color_meaning_id: int|null}>
     */
    private function extractManualEntries(array $data): array
    {
        if (is_array($data['entries'] ?? null)) {
            $entries = [];

            foreach ($data['entries'] as $entry) {
                if (! is_array($entry) || ! is_scalar($entry['code'] ?? null)) {
                    continue;
                }

                $code = trim((string) $entry['code']);

                if ($code === '') {
                    continue;
                }

                $entries[] = [
                    'code' => $code,
                    'dashboard_color_meaning_id' => is_numeric($entry['dashboard_color_meaning_id'] ?? null)
                        ? (int) $entry['dashboard_color_meaning_id']
                        : null,
                ];
            }

            if ($entries !== []) {
                return $entries;
            }
        }

        return array_map(
            fn (string $code): array => ['code' => $code, 'dashboard_color_meaning_id' => null],
            $this->extractManualCodes($data),
        );
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
