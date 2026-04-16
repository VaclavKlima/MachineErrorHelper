<?php

namespace App\Filament\Resources\DiagnosisRequests;

use App\Filament\Resources\DiagnosisRequests\Pages\CreateDiagnosisRequest;
use App\Filament\Resources\DiagnosisRequests\Pages\EditDiagnosisRequest;
use App\Filament\Resources\DiagnosisRequests\Pages\ListDiagnosisRequests;
use App\Filament\Resources\DiagnosisRequests\Pages\ViewDiagnosisRequest;
use App\Filament\Resources\DiagnosisRequests\Schemas\DiagnosisRequestForm;
use App\Filament\Resources\DiagnosisRequests\Schemas\DiagnosisRequestInfolist;
use App\Filament\Resources\DiagnosisRequests\Tables\DiagnosisRequestsTable;
use App\Models\DiagnosisRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiagnosisRequestResource extends Resource
{
    protected static ?string $model = DiagnosisRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return DiagnosisRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DiagnosisRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiagnosisRequestsTable::configure($table);
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
            'index' => ListDiagnosisRequests::route('/'),
            'create' => CreateDiagnosisRequest::route('/create'),
            'view' => ViewDiagnosisRequest::route('/{record}'),
            'edit' => EditDiagnosisRequest::route('/{record}/edit'),
        ];
    }
}
