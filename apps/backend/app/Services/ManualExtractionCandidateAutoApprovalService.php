<?php

namespace App\Services;

use App\Models\Manual;
use App\Models\ManualExtractionCandidate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ManualExtractionCandidateAutoApprovalService
{
    public function __construct(
        private readonly ManualExtractionCandidateApprovalService $approvalService,
    ) {}

    public function countForManual(?Manual $manual = null, float $minimumScore = 0.85): int
    {
        return $this->query($manual, $minimumScore)->count();
    }

    public function approveForManual(?Manual $manual = null, ?User $user = null, float $minimumScore = 0.85): int
    {
        $approved = 0;

        $this->query($manual, $minimumScore)
            ->orderByDesc('review_score')
            ->orderBy('id')
            ->chunkById(100, function ($candidates) use ($user, &$approved): void {
                foreach ($candidates as $candidate) {
                    $this->approvalService->approve($candidate, $user);
                    $approved++;
                }
            });

        return $approved;
    }

    public function query(?Manual $manual = null, float $minimumScore = 0.85): Builder
    {
        return ManualExtractionCandidate::query()
            ->where('status', 'pending')
            ->where('review_priority', 'high')
            ->where('review_score', '>=', $minimumScore)
            ->whereNull('noise_reason')
            ->whereNotNull('normalized_code')
            ->whereNotNull('meaning')
            ->where(fn (Builder $query): Builder => $query
                ->whereNotNull('module_key')
                ->orWhereNotNull('family'))
            ->when($manual, fn (Builder $query): Builder => $query->where('manual_id', $manual->id));
    }
}
