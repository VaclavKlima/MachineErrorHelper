<?php

namespace App\Filament\Resources\ErrorCodes;

use App\Filament\Resources\ErrorCodes\Pages\CreateErrorCode;
use App\Filament\Resources\ErrorCodes\Pages\EditErrorCode;
use App\Filament\Resources\ErrorCodes\Pages\ListErrorCodes;
use App\Filament\Resources\ErrorCodes\Pages\ViewErrorCode;
use App\Filament\Resources\ErrorCodes\Schemas\ErrorCodeForm;
use App\Filament\Resources\ErrorCodes\Schemas\ErrorCodeInfolist;
use App\Filament\Resources\ErrorCodes\Tables\ErrorCodesTable;
use App\Models\ErrorCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ErrorCodeResource extends Resource
{
    protected static ?string $model = ErrorCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return ErrorCodeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ErrorCodeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ErrorCodesTable::configure($table);
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
            'index' => ListErrorCodes::route('/'),
            'create' => CreateErrorCode::route('/create'),
            'view' => ViewErrorCode::route('/{record}'),
            'edit' => EditErrorCode::route('/{record}/edit'),
        ];
    }
}
