<?php

namespace App\Filament\Resources\SoftwareVersions\Pages;

use App\Filament\Resources\SoftwareVersions\SoftwareVersionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSoftwareVersion extends EditRecord
{
    protected static string $resource = SoftwareVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
