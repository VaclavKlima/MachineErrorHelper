<?php

namespace App\Filament\Resources\MachineCodePatterns\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MachineCodePatternInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('machine.name')
                    ->label('Machine'),
                TextEntry::make('name'),
                TextEntry::make('regex'),
                TextEntry::make('normalization_rule')
                    ->placeholder('-'),
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
