<?php

namespace App\Filament\Resources\ErrorCodeDefinitions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ErrorCodeDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('error_code_id')
                    ->relationship('errorCode', 'id')
                    ->required(),
                Select::make('manual_id')
                    ->relationship('manual', 'title'),
                Select::make('manual_chunk_id')
                    ->relationship('manualChunk', 'id'),
                TextInput::make('effective_from_version_id')
                    ->numeric(),
                TextInput::make('effective_to_version_id')
                    ->numeric(),
                TextInput::make('supersedes_definition_id')
                    ->numeric(),
                TextInput::make('source_page_number')
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('meaning')
                    ->columnSpanFull(),
                Textarea::make('cause')
                    ->columnSpanFull(),
                TextInput::make('severity'),
                Textarea::make('recommended_action')
                    ->columnSpanFull(),
                TextInput::make('source_confidence')
                    ->numeric(),
                TextInput::make('approval_status')
                    ->required()
                    ->default('candidate'),
                TextInput::make('approved_by')
                    ->numeric(),
                DateTimePicker::make('approved_at'),
            ]);
    }
}
