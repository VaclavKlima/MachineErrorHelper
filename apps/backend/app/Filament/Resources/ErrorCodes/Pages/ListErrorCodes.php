<?php

namespace App\Filament\Resources\ErrorCodes\Pages;

use App\Filament\Resources\ErrorCodes\ErrorCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListErrorCodes extends ListRecords
{
    protected static string $resource = ErrorCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
