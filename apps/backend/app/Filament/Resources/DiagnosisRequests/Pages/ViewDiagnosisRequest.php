<?php

namespace App\Filament\Resources\DiagnosisRequests\Pages;

use App\Filament\Resources\DiagnosisRequests\DiagnosisRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDiagnosisRequest extends ViewRecord
{
    protected static string $resource = DiagnosisRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
