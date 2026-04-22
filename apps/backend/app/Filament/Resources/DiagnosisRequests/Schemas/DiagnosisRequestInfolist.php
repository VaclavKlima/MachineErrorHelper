<?php

namespace App\Filament\Resources\DiagnosisRequests\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DiagnosisRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('public_id')
                    ->label('Scan ID'),
                TextEntry::make('machine.name')
                    ->label('Machine'),
                TextEntry::make('user.email')
                    ->label('User')
                    ->placeholder('-'),
                TextEntry::make('softwareVersion.version')
                    ->label('Software version')
                    ->placeholder('-'),
                TextEntry::make('selectedDiagnosticEntry.documentation_label')
                    ->label('Resolved code')
                    ->placeholder('-'),
                ImageEntry::make('screenshot_path')
                    ->label('Uploaded screenshot')
                    ->disk('local')
                    ->visibility('private')
                    ->url(fn ($record): ?string => $record->screenshot_url, true)
                    ->imageHeight(280)
                    ->columnSpanFull()
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('ai_detected_codes')
                    ->label('AI detected codes')
                    ->state(fn ($record): array => $record->ai_detected_codes ?? [])
                    ->listWithLineBreaks()
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('user_entered_codes')
                    ->label('User entered codes')
                    ->state(fn ($record): array => $record->user_entered_codes ?? [])
                    ->listWithLineBreaks()
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('raw_ocr_text')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('confidence')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('result_payload')
                    ->formatStateUsing(fn ($state): string => filled($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '' : '')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->label('Scanned at')
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
