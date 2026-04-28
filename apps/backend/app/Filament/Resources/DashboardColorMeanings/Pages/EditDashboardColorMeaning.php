<?php

namespace App\Filament\Resources\DashboardColorMeanings\Pages;

use App\Filament\Resources\DashboardColorMeanings\DashboardColorMeaningResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDashboardColorMeaning extends EditRecord
{
    protected static string $resource = DashboardColorMeaningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
