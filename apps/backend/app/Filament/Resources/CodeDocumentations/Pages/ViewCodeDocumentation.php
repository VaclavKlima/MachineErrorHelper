<?php

namespace App\Filament\Resources\CodeDocumentations\Pages;

use App\Filament\Resources\CodeDocumentations\CodeDocumentationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCodeDocumentation extends ViewRecord
{
    protected static string $resource = CodeDocumentationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
