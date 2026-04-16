<?php

namespace App\Filament\Resources\ManualExtractionCandidates\Pages;

use App\Filament\Resources\ManualExtractionCandidates\ManualExtractionCandidateResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditManualExtractionCandidate extends EditRecord
{
    protected static string $resource = ManualExtractionCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
