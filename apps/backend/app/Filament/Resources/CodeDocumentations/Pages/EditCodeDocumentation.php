<?php

namespace App\Filament\Resources\CodeDocumentations\Pages;

use App\Filament\Resources\CodeDocumentations\CodeDocumentationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCodeDocumentation extends EditRecord
{
    protected static string $resource = CodeDocumentationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
