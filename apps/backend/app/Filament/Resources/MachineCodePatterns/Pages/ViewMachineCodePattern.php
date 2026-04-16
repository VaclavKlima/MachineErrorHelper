<?php

namespace App\Filament\Resources\MachineCodePatterns\Pages;

use App\Filament\Resources\MachineCodePatterns\MachineCodePatternResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMachineCodePattern extends ViewRecord
{
    protected static string $resource = MachineCodePatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
