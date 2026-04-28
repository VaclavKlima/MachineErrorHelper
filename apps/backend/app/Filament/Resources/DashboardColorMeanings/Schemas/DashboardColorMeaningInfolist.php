<?php

namespace App\Filament\Resources\DashboardColorMeanings\Schemas;

use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DashboardColorMeaningInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('machine.name')
                    ->label('Machine'),
                ColorEntry::make('hex_color')
                    ->label('Color'),
                TextEntry::make('label'),
                TextEntry::make('ai_key')
                    ->label('AI key'),
                TextEntry::make('ai_aliases')
                    ->label('Generated AI aliases')
                    ->listWithLineBreaks()
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('priority')
                    ->numeric(),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
