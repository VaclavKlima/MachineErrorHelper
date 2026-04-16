<?php

namespace App\Filament\Resources\ErrorCodes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ErrorCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('machine_id')
                    ->relationship('machine', 'name')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('normalized_code')
                    ->required(),
                TextInput::make('family'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
