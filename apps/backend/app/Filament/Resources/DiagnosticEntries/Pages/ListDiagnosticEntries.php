<?php

namespace App\Filament\Resources\DiagnosticEntries\Pages;

use App\Filament\Resources\DiagnosticEntries\DiagnosticEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiagnosticEntries extends ListRecords
{
    protected static string $resource = DiagnosticEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
