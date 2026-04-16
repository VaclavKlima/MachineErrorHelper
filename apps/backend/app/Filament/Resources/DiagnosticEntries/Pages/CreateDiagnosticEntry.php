<?php

namespace App\Filament\Resources\DiagnosticEntries\Pages;

use App\Filament\Resources\DiagnosticEntries\DiagnosticEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiagnosticEntry extends CreateRecord
{
    protected static string $resource = DiagnosticEntryResource::class;
}
