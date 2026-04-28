<?php

namespace App\Filament\Resources\DashboardColorMeanings;

use App\Filament\Resources\DashboardColorMeanings\Pages\CreateDashboardColorMeaning;
use App\Filament\Resources\DashboardColorMeanings\Pages\EditDashboardColorMeaning;
use App\Filament\Resources\DashboardColorMeanings\Pages\ListDashboardColorMeanings;
use App\Filament\Resources\DashboardColorMeanings\Pages\ViewDashboardColorMeaning;
use App\Filament\Resources\DashboardColorMeanings\Schemas\DashboardColorMeaningForm;
use App\Filament\Resources\DashboardColorMeanings\Schemas\DashboardColorMeaningInfolist;
use App\Filament\Resources\DashboardColorMeanings\Tables\DashboardColorMeaningsTable;
use App\Models\DashboardColorMeaning;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DashboardColorMeaningResource extends Resource
{
    protected static ?string $model = DashboardColorMeaning::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|\UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'dashboard color';

    protected static ?string $pluralModelLabel = 'dashboard colors';

    public static function form(Schema $schema): Schema
    {
        return DashboardColorMeaningForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DashboardColorMeaningInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DashboardColorMeaningsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDashboardColorMeanings::route('/'),
            'create' => CreateDashboardColorMeaning::route('/create'),
            'view' => ViewDashboardColorMeaning::route('/{record}'),
            'edit' => EditDashboardColorMeaning::route('/{record}/edit'),
        ];
    }
}
