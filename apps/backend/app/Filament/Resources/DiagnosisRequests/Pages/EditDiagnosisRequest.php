<?php

namespace App\Filament\Resources\DiagnosisRequests\Pages;

use App\Filament\Resources\DiagnosisRequests\DiagnosisRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDiagnosisRequest extends EditRecord
{
    protected static string $resource = DiagnosisRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
