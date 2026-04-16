<?php

namespace App\Filament\Resources\MachineCodePatterns\Pages;

use App\Filament\Resources\MachineCodePatterns\MachineCodePatternResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMachineCodePatterns extends ListRecords
{
    protected static string $resource = MachineCodePatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
