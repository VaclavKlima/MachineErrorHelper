<?php

namespace App\Services;

use App\Models\DiagnosisRequest;
use App\Models\DiagnosticEntry;
use Illuminate\Database\Eloquent\Builder;

class DocumentationOpportunityService
{
    public function usedCodesQuery(): Builder
    {
        return DiagnosticEntry::query()
            ->with('machine:id,name')
            ->select('diagnostic_entries.*')
            ->withCount('codeDocumentations')
            ->selectSub(
                DiagnosisRequest::query()
                    ->selectRaw('count(*)')
                    ->whereNotNull('selected_diagnostic_entry_id')
                    ->whereColumn('selected_diagnostic_entry_id', 'diagnostic_entries.id'),
                'usage_count',
            )
            ->selectSub(
                DiagnosisRequest::query()
                    ->selectRaw('max(created_at)')
                    ->whereNotNull('selected_diagnostic_entry_id')
                    ->whereColumn('selected_diagnostic_entry_id', 'diagnostic_entries.id'),
                'last_seen_at',
            )
            ->whereIn(
                'diagnostic_entries.id',
                DiagnosisRequest::query()
                    ->select('selected_diagnostic_entry_id')
                    ->whereNotNull('selected_diagnostic_entry_id')
            );
    }

    public function missingDocumentationQuery(): Builder
    {
        return $this->usedCodesQuery()
            ->doesntHave('codeDocumentations');
    }

    public function documentedCodesQuery(): Builder
    {
        return $this->usedCodesQuery()
            ->has('codeDocumentations');
    }

    public function totalScansCount(): int
    {
        return DiagnosisRequest::query()->count();
    }

    public function resolvedScansCount(): int
    {
        return DiagnosisRequest::query()
            ->whereNotNull('selected_diagnostic_entry_id')
            ->count();
    }

    public function usedCodesCount(): int
    {
        return DiagnosisRequest::query()
            ->whereNotNull('selected_diagnostic_entry_id')
            ->distinct()
            ->count('selected_diagnostic_entry_id');
    }

    public function missingDocumentationCodesCount(): int
    {
        return (clone $this->missingDocumentationQuery())->count();
    }

    public function documentedUsedCodesCount(): int
    {
        return (clone $this->documentedCodesQuery())->count();
    }

    public function missingDocumentationScanHitsCount(): int
    {
        return DiagnosisRequest::query()
            ->whereIn(
                'selected_diagnostic_entry_id',
                $this->missingDocumentationQuery()->select('diagnostic_entries.id'),
            )
            ->count();
    }
}
