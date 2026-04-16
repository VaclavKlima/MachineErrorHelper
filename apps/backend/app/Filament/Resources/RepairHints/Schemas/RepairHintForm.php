<?php

namespace App\Filament\Resources\RepairHints\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RepairHintForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('machine_id')
                    ->relationship('machine', 'name')
                    ->required(),
                Select::make('error_code_id')
                    ->relationship('errorCode', 'id'),
                Select::make('error_code_definition_id')
                    ->relationship('errorCodeDefinition', 'title'),
                Select::make('diagnostic_entry_id')
                    ->relationship('diagnosticEntry', 'meaning')
                    ->searchable()
                    ->preload()
                    ->label('Diagnostic entry'),
                TextInput::make('title')
                    ->required(),
                Textarea::make('body')
                    ->columnSpanFull(),
                Textarea::make('steps')
                    ->columnSpanFull(),
                Textarea::make('safety_warning')
                    ->columnSpanFull(),
                Textarea::make('tools_required')
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(100),
            ]);
    }
}
