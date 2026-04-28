<?php

namespace App\Filament\Resources\DashboardColorMeanings\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DashboardColorMeaningForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('machine_id')
                    ->relationship('machine', 'name')
                    ->required(),
                ColorPicker::make('hex_color')
                    ->label('Color')
                    ->hex()
                    ->required(),
                TextInput::make('label')
                    ->helperText('Short user-facing name, for example Critical, Warning, or Past error.')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->helperText('Shown to the end user when this color is detected.')
                    ->columnSpanFull(),
                TextInput::make('priority')
                    ->helperText('Higher number means higher priority. Result cards with higher-priority colors are shown first.')
                    ->required()
                    ->numeric()
                    ->default(100),
                Toggle::make('is_active')
                    ->required()
                    ->default(true),
            ]);
    }
}
