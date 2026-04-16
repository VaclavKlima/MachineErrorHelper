<?php

namespace App\Filament\Resources\MachineCodePatterns\Pages;

use App\Filament\Resources\MachineCodePatterns\MachineCodePatternResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMachineCodePattern extends EditRecord
{
    protected static string $resource = MachineCodePatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
