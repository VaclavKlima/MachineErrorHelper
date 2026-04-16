<?php

namespace App\Filament\Resources\DiagnosisRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DiagnosisRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('public_id'),
                TextEntry::make('machine.name')
                    ->label('Machine'),
                TextEntry::make('user_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('softwareVersion.id')
                    ->label('Software version')
                    ->placeholder('-'),
                TextEntry::make('selected_error_code_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('selected_definition_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('screenshot_path')
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('raw_ocr_text')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('confidence')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('result_payload')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
