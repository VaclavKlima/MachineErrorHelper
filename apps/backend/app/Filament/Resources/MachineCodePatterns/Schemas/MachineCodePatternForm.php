<?php

namespace App\Filament\Resources\MachineCodePatterns\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MachineCodePatternForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('machine_id')
                    ->relationship('machine', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('regex')
                    ->required(),
                TextInput::make('normalization_rule'),
                TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(100),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
