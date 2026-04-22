<?php

namespace App\Services;

use App\Models\DiagnosticEntry;
use App\Models\ManualExtractionCandidate;
use Illuminate\Support\Facades\DB;

class ManualExtractionCandidatePublishingService
{
    public function publish(ManualExtractionCandidate $candidate): ?DiagnosticEntry
    {
        if (! $this->isPublishable($candidate)) {
            return null;
        }

        return DB::transaction(function () use ($candidate): DiagnosticEntry {
            $moduleKey = $candidate->module_key ?: $candidate->family;
            $primaryCode = $candidate->primary_code ?: $candidate->code;
            $normalizedCode = $candidate->normalized_code;

            $entry = DiagnosticEntry::withTrashed()->firstOrNew([
                'machine_id' => $candidate->machine_id,
                'manual_id' => $candidate->manual_id,
                'module_key' => $moduleKey,
                'primary_code_normalized' => $normalizedCode,
            ]);

            $entry->fill([
                'machine_id' => $candidate->machine_id,
                'manual_id' => $candidate->manual_id,
                'manual_page_id' => $candidate->manual_page_id,
                'manual_chunk_id' => $candidate->manual_chunk_id,
                'manual_extraction_candidate_id' => $candidate->id,
                'module_key' => $moduleKey,
                'section_title' => $candidate->section_title,
                'primary_code' => $primaryCode,
                'primary_code_normalized' => $normalizedCode,
                'context' => $candidate->context ?: [
                    'module' => $candidate->module_key ?: $candidate->family,
                    'section_title' => $candidate->section_title,
                ],
                'identifiers' => $candidate->identifiers ?: [
                    'code' => $candidate->normalized_code ?: $candidate->code,
                ],
                'title' => $candidate->title ?: $candidate->code ?: 'Untitled error code',
                'meaning' => $candidate->meaning,
                'cause' => $candidate->cause,
                'recommended_action' => $candidate->recommended_action,
                'source_text' => $candidate->source_text,
                'source_page_number' => $candidate->source_page_number,
                'extractor' => $candidate->extractor,
                'confidence' => $candidate->confidence,
                'status' => $entry->exists && $entry->status === 'disabled' ? 'disabled' : 'active',
                'approved_by' => null,
                'approved_at' => now(),
                'metadata' => $candidate->metadata,
            ]);

            $entry->save();

            $candidate->forceFill([
                'status' => 'published',
                'reviewed_by' => null,
                'reviewed_at' => now(),
            ])->save();

            return $entry;
        });
    }

    private function isPublishable(ManualExtractionCandidate $candidate): bool
    {
        return $candidate->status !== 'ignored'
            && $candidate->noise_reason === null
            && filled($candidate->normalized_code)
            && filled($candidate->meaning)
            && filled($candidate->module_key ?: $candidate->family);
    }
}
