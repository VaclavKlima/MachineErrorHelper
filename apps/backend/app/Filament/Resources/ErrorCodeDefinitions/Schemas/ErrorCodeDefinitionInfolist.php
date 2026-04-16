<?php

namespace App\Filament\Resources\ErrorCodeDefinitions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ErrorCodeDefinitionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('errorCode.id')
                    ->label('Error code'),
                TextEntry::make('manual.title')
                    ->label('Manual')
                    ->placeholder('-'),
                TextEntry::make('manualChunk.id')
                    ->label('Manual chunk')
                    ->placeholder('-'),
                TextEntry::make('effective_from_version_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('effective_to_version_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('supersedes_definition_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('source_page_number')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('title'),
                TextEntry::make('meaning')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('cause')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('severity')
                    ->placeholder('-'),
                TextEntry::make('recommended_action')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('source_confidence')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('approval_status'),
                TextEntry::make('approved_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('approved_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
