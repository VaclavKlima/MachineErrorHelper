<?php

namespace App\Services;

use App\Models\DiagnosticEntry;
use App\Models\ManualExtractionCandidate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManualExtractionCandidateApprovalService
{
    public function approve(ManualExtractionCandidate $candidate, ?User $user = null): DiagnosticEntry
    {
        return DB::transaction(function () use ($candidate, $user) {
            $moduleKey = $candidate->module_key ?: $candidate->family;
            $primaryCode = $candidate->primary_code ?: $candidate->code;
            $normalizedCode = $candidate->normalized_code;

            $entry = DiagnosticEntry::updateOrCreate(
                [
                    'machine_id' => $candidate->machine_id,
                    'manual_id' => $candidate->manual_id,
                    'module_key' => $moduleKey,
                    'primary_code_normalized' => $normalizedCode,
                ],
                [
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
                    'status' => 'approved',
                    'approved_by' => $user?->id,
                    'approved_at' => now(),
                    'metadata' => $candidate->metadata,
                ],
            );

            $candidate->forceFill([
                'status' => 'approved',
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
            ])->save();

            return $entry;
        });
    }

    public function reject(ManualExtractionCandidate $candidate, ?User $user = null): void
    {
        $candidate->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $user?->id,
            'reviewed_at' => now(),
        ])->save();
    }
}
