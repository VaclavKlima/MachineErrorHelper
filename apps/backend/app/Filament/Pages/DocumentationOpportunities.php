<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DocumentationOpportunityStats;
use App\Filament\Widgets\MissingDocumentationCodesTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class DocumentationOpportunities extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Documentation opportunities';

    protected static ?int $navigationSort = 21;

    protected static ?string $title = 'Documentation opportunities';

    protected string $view = 'filament.pages.documentation-opportunities';

    protected function getHeaderWidgets(): array
    {
        return [
            DocumentationOpportunityStats::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            MissingDocumentationCodesTable::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 4;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
