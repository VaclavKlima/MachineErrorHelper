<?php

namespace App\Filament\Resources\DiagnosticEntries\Pages;

use App\Filament\Resources\DiagnosticEntries\DiagnosticEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDiagnosticEntry extends EditRecord
{
    protected static string $resource = DiagnosticEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
