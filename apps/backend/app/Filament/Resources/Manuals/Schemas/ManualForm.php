<?php

namespace App\Filament\Resources\Manuals\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ManualForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('machine_id')
                    ->relationship('machine', 'name')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Select::make('coverage_mode')
                    ->options([
                        'complete' => 'Complete manual',
                        'delta' => 'Delta / changed codes only',
                        'supplement' => 'Supplement',
                    ])
                    ->required()
                    ->default('complete'),
                Select::make('language')
                    ->options([
                        'cs' => 'Czech',
                        'en' => 'English',
                    ])
                    ->required()
                    ->default('cs'),
                FileUpload::make('file_path')
                    ->label('PDF manual')
                    ->disk('local')
                    ->directory('manuals/uploads')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(102400)
                    ->preserveFilenames()
                    ->required()
                    ->columnSpanFull(),
                Hidden::make('file_hash'),
                TextInput::make('page_count')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                Textarea::make('source_notes')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->default('uploaded')
                    ->disabled()
                    ->dehydrated(),
            ]);
    }
}
