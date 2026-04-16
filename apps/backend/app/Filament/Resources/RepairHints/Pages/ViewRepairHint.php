<?php

namespace App\Filament\Resources\RepairHints\Pages;

use App\Filament\Resources\RepairHints\RepairHintResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRepairHint extends ViewRecord
{
    protected static string $resource = RepairHintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
