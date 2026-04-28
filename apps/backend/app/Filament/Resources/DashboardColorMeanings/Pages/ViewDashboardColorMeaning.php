<?php

namespace App\Filament\Resources\DashboardColorMeanings\Pages;

use App\Filament\Resources\DashboardColorMeanings\DashboardColorMeaningResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDashboardColorMeaning extends ViewRecord
{
    protected static string $resource = DashboardColorMeaningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
