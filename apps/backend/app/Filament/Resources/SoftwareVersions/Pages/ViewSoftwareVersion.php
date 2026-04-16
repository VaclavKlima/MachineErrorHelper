<?php

namespace App\Filament\Resources\SoftwareVersions\Pages;

use App\Filament\Resources\SoftwareVersions\SoftwareVersionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSoftwareVersion extends ViewRecord
{
    protected static string $resource = SoftwareVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
