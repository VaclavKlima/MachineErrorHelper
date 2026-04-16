<?php

namespace App\Filament\Resources\MachineCodePatterns;

use App\Filament\Resources\MachineCodePatterns\Pages\CreateMachineCodePattern;
use App\Filament\Resources\MachineCodePatterns\Pages\EditMachineCodePattern;
use App\Filament\Resources\MachineCodePatterns\Pages\ListMachineCodePatterns;
use App\Filament\Resources\MachineCodePatterns\Pages\ViewMachineCodePattern;
use App\Filament\Resources\MachineCodePatterns\Schemas\MachineCodePatternForm;
use App\Filament\Resources\MachineCodePatterns\Schemas\MachineCodePatternInfolist;
use App\Filament\Resources\MachineCodePatterns\Tables\MachineCodePatternsTable;
use App\Models\MachineCodePattern;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MachineCodePatternResource extends Resource
{
    protected static ?string $model = MachineCodePattern::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return MachineCodePatternForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MachineCodePatternInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MachineCodePatternsTable::configure($table);
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
            'index' => ListMachineCodePatterns::route('/'),
            'create' => CreateMachineCodePattern::route('/create'),
            'view' => ViewMachineCodePattern::route('/{record}'),
            'edit' => EditMachineCodePattern::route('/{record}/edit'),
        ];
    }
}
