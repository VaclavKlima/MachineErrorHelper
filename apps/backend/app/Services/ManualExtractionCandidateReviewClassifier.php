<?php

namespace App\Services;

use Illuminate\Support\Str;

class ManualExtractionCandidateReviewClassifier
{
    /**
     * @param  array<string, mixed>  $candidate
     * @return array{status: string, review_score: float, review_priority: string, noise_reason: string|null}
     */
    public function classify(array $candidate): array
    {
        $meaning = $this->clean((string) ($candidate['meaning'] ?? $candidate['title'] ?? ''));
        $action = $this->clean((string) ($candidate['recommended_action'] ?? ''));
        $moduleKey = $this->clean((string) ($candidate['module_key'] ?? $candidate['family'] ?? ''));
        $extractor = (string) ($candidate['extractor'] ?? '');
        $identifiers = is_array($candidate['identifiers'] ?? null) ? $candidate['identifiers'] : [];
        $confidence = is_numeric($candidate['confidence'] ?? null) ? (float) $candidate['confidence'] : 0.45;

        $noiseReason = $this->noiseReason($meaning, $action, $moduleKey, $identifiers);
        $score = $this->score($meaning, $action, $moduleKey, $identifiers, $extractor, $confidence, $noiseReason);
        $priority = match (true) {
            $score >= 0.78 => 'high',
            $score >= 0.55 => 'normal',
            default => 'low',
        };

        return [
            'status' => $noiseReason !== null ? 'ignored' : 'pending',
            'review_score' => $score,
            'review_priority' => $priority,
            'noise_reason' => $noiseReason,
        ];
    }

    /**
     * @param  array<string, mixed>  $identifiers
     */
    private function noiseReason(string $meaning, string $action, string $moduleKey, array $identifiers): ?string
    {
        if ($meaning === '' || in_array($meaning, ['-', '—'], true)) {
            return 'empty_meaning';
        }

        if (preg_match('/^SGE_IDN_\d+$/u', $meaning) === 1 && ($action === '' || in_array($action, ['-', '—'], true))) {
            return 'placeholder_only';
        }

        if (preg_match('/^[A-Z](?:\s+[A-Z])+$/u', $meaning) === 1) {
            return 'watermark_fragment';
        }

        if ($moduleKey === '' && count($identifiers) <= 1 && mb_strlen($meaning) < 18) {
            return 'too_little_context';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $identifiers
     */
    private function score(
        string $meaning,
        string $action,
        string $moduleKey,
        array $identifiers,
        string $extractor,
        float $confidence,
        ?string $noiseReason,
    ): float {
        $score = max(min($confidence, 1), 0) * 0.55;

        if ($moduleKey !== '') {
            $score += 0.14;
        } else {
            $score -= 0.12;
        }

        if (isset($identifiers['code']) || isset($identifiers['spn'])) {
            $score += 0.1;
        }

        if (count($identifiers) > 1) {
            $score += 0.08;
        }

        if ($action !== '' && ! in_array($action, ['-', '—'], true)) {
            $score += 0.12;
        }

        if (mb_strlen($meaning) >= 25) {
            $score += 0.08;
        }

        if (str_contains($extractor, 'gemini')) {
            $score += 0.08;
        }

        if (preg_match('/^SGE_IDN_\d+/u', $meaning) === 1) {
            $score -= 0.28;
        }

        if ($noiseReason !== null) {
            $score = min($score, 0.25);
        }

        return round(max(min($score, 0.99), 0), 4);
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
