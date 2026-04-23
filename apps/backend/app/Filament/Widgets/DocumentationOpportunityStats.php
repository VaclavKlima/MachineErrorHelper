<?php

namespace App\Filament\Widgets;

use App\Services\DocumentationOpportunityService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DocumentationOpportunityStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Documentation dashboard';

    protected function getStats(): array
    {
        $opportunities = app(DocumentationOpportunityService::class);

        return [
            Stat::make('Total scans', (string) $opportunities->totalScansCount())
                ->color('gray'),
            Stat::make('Resolved scans', (string) $opportunities->resolvedScansCount())
                ->color('success'),
            Stat::make('Used codes', (string) $opportunities->usedCodesCount())
                ->color('warning'),
            Stat::make('Documented codes', (string) $opportunities->documentedUsedCodesCount())
                ->color('info'),
            Stat::make('Missing docs', (string) $opportunities->missingDocumentationCodesCount())
                ->description($opportunities->missingDocumentationScanHitsCount().' scan hits')
                ->color('danger'),
        ];
    }
}
