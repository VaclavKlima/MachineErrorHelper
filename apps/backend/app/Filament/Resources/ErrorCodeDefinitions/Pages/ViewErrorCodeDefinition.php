<?php

namespace App\Filament\Resources\ErrorCodeDefinitions\Pages;

use App\Filament\Resources\ErrorCodeDefinitions\ErrorCodeDefinitionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewErrorCodeDefinition extends ViewRecord
{
    protected static string $resource = ErrorCodeDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
