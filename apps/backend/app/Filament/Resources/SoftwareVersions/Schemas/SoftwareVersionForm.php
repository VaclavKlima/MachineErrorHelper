<?php

namespace App\Filament\Resources\SoftwareVersions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SoftwareVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('machine_id')
                    ->relationship('machine', 'name')
                    ->required(),
                TextInput::make('version')
                    ->required(),
                DatePicker::make('released_at'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(100),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
