<?php

namespace App\Filament\Resources\RepairHints\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RepairHintInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('machine.name')
                    ->label('Machine'),
                TextEntry::make('errorCode.id')
                    ->label('Error code')
                    ->placeholder('-'),
                TextEntry::make('errorCodeDefinition.title')
                    ->label('Error code definition')
                    ->placeholder('-'),
                TextEntry::make('title'),
                TextEntry::make('body')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('steps')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('safety_warning')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('tools_required')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('is_published')
                    ->boolean(),
                TextEntry::make('sort_order')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
