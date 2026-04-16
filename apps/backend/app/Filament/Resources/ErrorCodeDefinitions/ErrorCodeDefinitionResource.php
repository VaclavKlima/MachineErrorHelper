<?php

namespace App\Filament\Resources\ErrorCodeDefinitions;

use App\Filament\Resources\ErrorCodeDefinitions\Pages\CreateErrorCodeDefinition;
use App\Filament\Resources\ErrorCodeDefinitions\Pages\EditErrorCodeDefinition;
use App\Filament\Resources\ErrorCodeDefinitions\Pages\ListErrorCodeDefinitions;
use App\Filament\Resources\ErrorCodeDefinitions\Pages\ViewErrorCodeDefinition;
use App\Filament\Resources\ErrorCodeDefinitions\Schemas\ErrorCodeDefinitionForm;
use App\Filament\Resources\ErrorCodeDefinitions\Schemas\ErrorCodeDefinitionInfolist;
use App\Filament\Resources\ErrorCodeDefinitions\Tables\ErrorCodeDefinitionsTable;
use App\Models\ErrorCodeDefinition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ErrorCodeDefinitionResource extends Resource
{
    protected static ?string $model = ErrorCodeDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return ErrorCodeDefinitionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ErrorCodeDefinitionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ErrorCodeDefinitionsTable::configure($table);
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
            'index' => ListErrorCodeDefinitions::route('/'),
            'create' => CreateErrorCodeDefinition::route('/create'),
            'view' => ViewErrorCodeDefinition::route('/{record}'),
            'edit' => EditErrorCodeDefinition::route('/{record}/edit'),
        ];
    }
}
