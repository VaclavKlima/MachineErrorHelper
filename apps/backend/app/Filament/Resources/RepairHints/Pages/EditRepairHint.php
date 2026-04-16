<?php

namespace App\Filament\Resources\RepairHints\Pages;

use App\Filament\Resources\RepairHints\RepairHintResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRepairHint extends EditRecord
{
    protected static string $resource = RepairHintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
