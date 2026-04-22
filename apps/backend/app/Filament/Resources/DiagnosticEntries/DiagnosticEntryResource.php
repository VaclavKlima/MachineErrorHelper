<?php

namespace App\Filament\Resources\DiagnosticEntries;

use App\Filament\Resources\DiagnosticEntries\Pages\CreateDiagnosticEntry;
use App\Filament\Resources\DiagnosticEntries\Pages\EditDiagnosticEntry;
use App\Filament\Resources\DiagnosticEntries\Pages\ListDiagnosticEntries;
use App\Filament\Resources\DiagnosticEntries\Pages\ViewDiagnosticEntry;
use App\Filament\Resources\DiagnosticEntries\Schemas\DiagnosticEntryForm;
use App\Filament\Resources\DiagnosticEntries\Schemas\DiagnosticEntryInfolist;
use App\Filament\Resources\DiagnosticEntries\Tables\DiagnosticEntriesTable;
use App\Models\DiagnosticEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiagnosticEntryResource extends Resource
{
    protected static ?string $model = DiagnosticEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Error codes';

    protected static ?string $modelLabel = 'error code';

    protected static ?string $pluralModelLabel = 'error codes';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 35;

    public static function form(Schema $schema): Schema
    {
        return DiagnosticEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DiagnosticEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiagnosticEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiagnosticEntries::route('/'),
            'create' => CreateDiagnosticEntry::route('/create'),
            'view' => ViewDiagnosticEntry::route('/{record}'),
            'edit' => EditDiagnosticEntry::route('/{record}/edit'),
        ];
    }
}
