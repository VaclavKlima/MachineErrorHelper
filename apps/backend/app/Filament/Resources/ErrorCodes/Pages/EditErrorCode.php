<?php

namespace App\Filament\Resources\ErrorCodes\Pages;

use App\Filament\Resources\ErrorCodes\ErrorCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditErrorCode extends EditRecord
{
    protected static string $resource = ErrorCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
