<?php

namespace App\Filament\Resources\SoftwareVersions\Pages;

use App\Filament\Resources\SoftwareVersions\SoftwareVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSoftwareVersions extends ListRecords
{
    protected static string $resource = SoftwareVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
