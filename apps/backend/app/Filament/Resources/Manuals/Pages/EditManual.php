<?php

namespace App\Filament\Resources\Manuals\Pages;

use App\Filament\Resources\Manuals\ManualResource;
use App\Services\ManualImportService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditManual extends EditRecord
{
    protected static string $resource = ManualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['file_path'] ?? null) && $data['file_path'] !== $this->record->file_path) {
            $path = Storage::disk('local')->path($data['file_path']);
            $data['file_hash'] = hash_file('sha256', $path);
            $data['page_count'] = app(ManualImportService::class)->detectPageCount($path);
            $data['status'] = 'uploaded';
        }

        return $data;
    }
}
