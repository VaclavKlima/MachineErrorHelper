<?php

namespace App\Filament\Resources\CodeDocumentations;

use App\Filament\Resources\CodeDocumentations\Pages\CreateCodeDocumentation;
use App\Filament\Resources\CodeDocumentations\Pages\EditCodeDocumentation;
use App\Filament\Resources\CodeDocumentations\Pages\ListCodeDocumentations;
use App\Filament\Resources\CodeDocumentations\Pages\ViewCodeDocumentation;
use App\Filament\Resources\CodeDocumentations\Schemas\CodeDocumentationForm;
use App\Filament\Resources\CodeDocumentations\Schemas\CodeDocumentationInfolist;
use App\Filament\Resources\CodeDocumentations\Tables\CodeDocumentationsTable;
use App\Models\CodeDocumentation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CodeDocumentationResource extends Resource
{
    protected static ?string $model = CodeDocumentation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Documentation';

    protected static ?string $modelLabel = 'documentation';

    protected static ?string $pluralModelLabel = 'documentation';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 36;

    public static function form(Schema $schema): Schema
    {
        return CodeDocumentationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CodeDocumentationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CodeDocumentationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCodeDocumentations::route('/'),
            'create' => CreateCodeDocumentation::route('/create'),
            'view' => ViewCodeDocumentation::route('/{record}'),
            'edit' => EditCodeDocumentation::route('/{record}/edit'),
        ];
    }
}
