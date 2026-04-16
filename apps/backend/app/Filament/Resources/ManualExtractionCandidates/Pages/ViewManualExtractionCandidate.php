<?php

namespace App\Filament\Resources\ManualExtractionCandidates\Pages;

use App\Filament\Resources\ManualExtractionCandidates\ManualExtractionCandidateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewManualExtractionCandidate extends ViewRecord
{
    protected static string $resource = ManualExtractionCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
