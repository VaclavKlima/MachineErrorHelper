<?php

namespace App\Filament\Resources\RepairHints\Pages;

use App\Filament\Resources\RepairHints\RepairHintResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRepairHints extends ListRecords
{
    protected static string $resource = RepairHintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
