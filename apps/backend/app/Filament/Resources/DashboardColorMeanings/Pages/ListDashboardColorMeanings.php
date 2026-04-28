<?php

namespace App\Filament\Resources\DashboardColorMeanings\Pages;

use App\Filament\Resources\DashboardColorMeanings\DashboardColorMeaningResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDashboardColorMeanings extends ListRecords
{
    protected static string $resource = DashboardColorMeaningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
