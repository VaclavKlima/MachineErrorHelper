<?php

namespace App\Filament\Resources\RepairHints;

use App\Filament\Resources\RepairHints\Pages\CreateRepairHint;
use App\Filament\Resources\RepairHints\Pages\EditRepairHint;
use App\Filament\Resources\RepairHints\Pages\ListRepairHints;
use App\Filament\Resources\RepairHints\Pages\ViewRepairHint;
use App\Filament\Resources\RepairHints\Schemas\RepairHintForm;
use App\Filament\Resources\RepairHints\Schemas\RepairHintInfolist;
use App\Filament\Resources\RepairHints\Tables\RepairHintsTable;
use App\Models\RepairHint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RepairHintResource extends Resource
{
    protected static ?string $model = RepairHint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return RepairHintForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RepairHintInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RepairHintsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRepairHints::route('/'),
            'create' => CreateRepairHint::route('/create'),
            'view' => ViewRepairHint::route('/{record}'),
            'edit' => EditRepairHint::route('/{record}/edit'),
        ];
    }
}
