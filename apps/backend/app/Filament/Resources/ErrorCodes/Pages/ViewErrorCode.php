<?php

namespace App\Filament\Resources\ErrorCodes\Pages;

use App\Filament\Resources\ErrorCodes\ErrorCodeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewErrorCode extends ViewRecord
{
    protected static string $resource = ErrorCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
