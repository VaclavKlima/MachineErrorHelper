<?php

namespace App\Filament\Resources\ManualExtractionCandidates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ManualExtractionCandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status')
                    ->options([
                        'pending' => 'Pending review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'ignored' => 'Ignored',
                    ])
                    ->required(),
                TextInput::make('code')
                    ->required()
                    ->maxLength(255),
                TextInput::make('normalized_code')
                    ->required()
                    ->maxLength(255),
                TextInput::make('family')
                    ->maxLength(255),
                TextInput::make('module_key')
                    ->label('Module / context key')
                    ->maxLength(255),
                TextInput::make('section_title')
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('primary_code')
                    ->maxLength(255),
                KeyValue::make('identifiers')
                    ->keyLabel('Identifier')
                    ->valueLabel('Value')
                    ->columnSpanFull(),
                KeyValue::make('context')
                    ->keyLabel('Context')
                    ->valueLabel('Value')
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->required()
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
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                TextInput::make('source_page_number')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('extractor')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('confidence')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('review_score')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                Select::make('review_priority')
                    ->options([
                        'high' => 'High',
                        'normal' => 'Normal',
                        'low' => 'Low',
                    ])
                    ->required(),
                TextInput::make('noise_reason')
                    ->maxLength(255),
            ]);
    }
}
