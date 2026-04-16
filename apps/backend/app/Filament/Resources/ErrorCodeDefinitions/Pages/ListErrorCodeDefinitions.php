<?php

namespace App\Filament\Resources\ErrorCodeDefinitions\Pages;

use App\Filament\Resources\ErrorCodeDefinitions\ErrorCodeDefinitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListErrorCodeDefinitions extends ListRecords
{
    protected static string $resource = ErrorCodeDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
