<?php

namespace App\Filament\Resources\DiagnosisRequests\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DiagnosisRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('public_id')
                    ->required(),
                Select::make('machine_id')
                    ->relationship('machine', 'name')
                    ->required(),
                TextInput::make('user_id')
                    ->numeric(),
                Select::make('software_version_id')
                    ->relationship('softwareVersion', 'id'),
                TextInput::make('selected_error_code_id')
                    ->numeric(),
                TextInput::make('selected_definition_id')
                    ->numeric(),
                TextInput::make('screenshot_path'),
                TextInput::make('status')
                    ->required()
                    ->default('uploaded'),
                Textarea::make('raw_ocr_text')
                    ->columnSpanFull(),
                TextInput::make('confidence')
                    ->numeric(),
                Textarea::make('result_payload')
                    ->columnSpanFull(),
            ]);
    }
}
