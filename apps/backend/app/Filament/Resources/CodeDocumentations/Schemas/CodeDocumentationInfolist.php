<?php

namespace App\Filament\Resources\CodeDocumentations\Schemas;

use App\Models\CodeDocumentation;
use App\Models\DiagnosticEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CodeDocumentationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title')
                    ->columnSpanFull(),
                TextEntry::make('linked_codes')
                    ->label('Linked codes')
                    ->state(fn (CodeDocumentation $record): array => $record->diagnosticEntries
                        ->map(fn (DiagnosticEntry $entry): string => $entry->documentation_label)
                        ->all())
                    ->listWithLineBreaks()
                    ->columnSpanFull(),
                TextEntry::make('content')
                    ->label('Documentation body')
                    ->prose()
                    ->columnSpanFull(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
