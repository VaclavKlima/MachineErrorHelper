<?php

namespace App\Filament\Resources\Manuals\Pages;

use App\Filament\Resources\Manuals\ManualResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewManual extends ViewRecord
{
    protected static string $resource = ManualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
