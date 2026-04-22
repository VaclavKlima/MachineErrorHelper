<?php

namespace App\Filament\Resources\DiagnosticEntries\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DiagnosticEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('machine_id')
                    ->relationship('machine', 'name')
                    ->required(),
                Select::make('status')
                    ->label('State')
                    ->options([
                        'active' => 'Active',
                        'disabled' => 'Disabled',
                    ])
                    ->required()
                    ->default('active'),
                TextInput::make('module_key')
                    ->label('Module / context key')
                    ->maxLength(255),
                TextInput::make('primary_code')
                    ->maxLength(255),
                TextInput::make('primary_code_normalized')
                    ->maxLength(255),
                TextInput::make('section_title')
                    ->maxLength(255)
                    ->columnSpanFull(),
                KeyValue::make('identifiers')
                    ->keyLabel('Identifier')
                    ->valueLabel('Value')
                    ->required()
                    ->columnSpanFull(),
                KeyValue::make('context')
                    ->keyLabel('Context')
                    ->valueLabel('Value')
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('meaning')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('cause')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('recommended_action')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('source_text')
                    ->rows(5)
                    ->columnSpanFull(),
                TextInput::make('source_page_number')
                    ->numeric(),
            ]);
    }
}
