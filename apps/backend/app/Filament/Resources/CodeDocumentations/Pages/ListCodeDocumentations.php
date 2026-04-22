<?php

namespace App\Filament\Resources\CodeDocumentations\Pages;

use App\Filament\Resources\CodeDocumentations\CodeDocumentationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCodeDocumentations extends ListRecords
{
    protected static string $resource = CodeDocumentationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
