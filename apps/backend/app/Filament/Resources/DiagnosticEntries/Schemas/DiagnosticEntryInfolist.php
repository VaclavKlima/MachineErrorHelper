<?php

namespace App\Filament\Resources\DiagnosticEntries\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DiagnosticEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('machine.name'),
                TextEntry::make('manual.title'),
                TextEntry::make('module_key')
                    ->label('Module / context key'),
                TextEntry::make('primary_code'),
                TextEntry::make('section_title')
                    ->columnSpanFull(),
                KeyValueEntry::make('identifiers')
                    ->columnSpanFull(),
                KeyValueEntry::make('context')
                    ->columnSpanFull(),
                TextEntry::make('meaning')
                    ->columnSpanFull(),
                TextEntry::make('recommended_action')
                    ->columnSpanFull(),
                TextEntry::make('source_text')
                    ->columnSpanFull(),
                TextEntry::make('source_page_number'),
                TextEntry::make('confidence'),
                TextEntry::make('approved_at'),
            ]);
    }
}
