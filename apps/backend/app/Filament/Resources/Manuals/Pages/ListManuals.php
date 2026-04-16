<?php

namespace App\Filament\Resources\Manuals\Pages;

use App\Filament\Resources\Manuals\ManualResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListManuals extends ListRecords
{
    protected static string $resource = ManualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
