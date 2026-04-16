<?php

namespace App\Filament\Resources\SoftwareVersions;

use App\Filament\Resources\SoftwareVersions\Pages\CreateSoftwareVersion;
use App\Filament\Resources\SoftwareVersions\Pages\EditSoftwareVersion;
use App\Filament\Resources\SoftwareVersions\Pages\ListSoftwareVersions;
use App\Filament\Resources\SoftwareVersions\Pages\ViewSoftwareVersion;
use App\Filament\Resources\SoftwareVersions\Schemas\SoftwareVersionForm;
use App\Filament\Resources\SoftwareVersions\Schemas\SoftwareVersionInfolist;
use App\Filament\Resources\SoftwareVersions\Tables\SoftwareVersionsTable;
use App\Models\SoftwareVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SoftwareVersionResource extends Resource
{
    protected static ?string $model = SoftwareVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return SoftwareVersionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SoftwareVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SoftwareVersionsTable::configure($table);
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
            'index' => ListSoftwareVersions::route('/'),
            'create' => CreateSoftwareVersion::route('/create'),
            'view' => ViewSoftwareVersion::route('/{record}'),
            'edit' => EditSoftwareVersion::route('/{record}/edit'),
        ];
    }
}
