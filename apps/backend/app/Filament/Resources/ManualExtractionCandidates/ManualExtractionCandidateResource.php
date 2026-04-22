<?php

namespace App\Filament\Resources\ManualExtractionCandidates;

use App\Filament\Resources\ManualExtractionCandidates\Pages\EditManualExtractionCandidate;
use App\Filament\Resources\ManualExtractionCandidates\Pages\ListManualExtractionCandidates;
use App\Filament\Resources\ManualExtractionCandidates\Pages\ViewManualExtractionCandidate;
use App\Filament\Resources\ManualExtractionCandidates\Schemas\ManualExtractionCandidateForm;
use App\Filament\Resources\ManualExtractionCandidates\Schemas\ManualExtractionCandidateInfolist;
use App\Filament\Resources\ManualExtractionCandidates\Tables\ManualExtractionCandidatesTable;
use App\Models\ManualExtractionCandidate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ManualExtractionCandidateResource extends Resource
{
    protected static ?string $model = ManualExtractionCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Extraction log';

    protected static ?string $modelLabel = 'extracted code';

    protected static ?string $pluralModelLabel = 'extracted codes';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return ManualExtractionCandidateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ManualExtractionCandidateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ManualExtractionCandidatesTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListManualExtractionCandidates::route('/'),
            'view' => ViewManualExtractionCandidate::route('/{record}'),
            'edit' => EditManualExtractionCandidate::route('/{record}/edit'),
        ];
    }
}
