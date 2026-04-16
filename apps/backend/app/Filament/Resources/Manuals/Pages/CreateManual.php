<?php

namespace App\Filament\Resources\Manuals\Pages;

use App\Filament\Resources\Manuals\ManualResource;
use App\Services\ManualImportService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateManual extends CreateRecord
{
    protected static string $resource = ManualResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->fillFileMetadata($data);
    }

    private function fillFileMetadata(array $data): array
    {
        $path = Storage::disk('local')->path($data['file_path']);
        $data['file_hash'] = hash_file('sha256', $path);
        $data['page_count'] = app(ManualImportService::class)->detectPageCount($path);
        $data['status'] = 'uploaded';

        return $data;
    }
}
