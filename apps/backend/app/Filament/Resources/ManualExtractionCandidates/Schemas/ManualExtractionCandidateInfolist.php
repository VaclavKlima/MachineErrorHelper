<?php

namespace App\Filament\Resources\ManualExtractionCandidates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Schemas\Schema;

class ManualExtractionCandidateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('machine.name'),
                TextEntry::make('manual.title'),
                TextEntry::make('code'),
                TextEntry::make('normalized_code'),
                TextEntry::make('module_key')
                    ->label('Module / context key'),
                TextEntry::make('section_title')
                    ->columnSpanFull(),
                KeyValueEntry::make('identifiers')
                    ->columnSpanFull(),
                KeyValueEntry::make('context')
                    ->columnSpanFull(),
                TextEntry::make('title')
                    ->columnSpanFull(),
                TextEntry::make('meaning')
                    ->columnSpanFull(),
                TextEntry::make('recommended_action')
                    ->columnSpanFull(),
                TextEntry::make('source_text')
                    ->columnSpanFull(),
                TextEntry::make('source_page_number'),
                TextEntry::make('extractor'),
                TextEntry::make('confidence'),
                TextEntry::make('review_score'),
                TextEntry::make('review_priority')
                    ->badge(),
                TextEntry::make('noise_reason'),
            ]);
    }
}
