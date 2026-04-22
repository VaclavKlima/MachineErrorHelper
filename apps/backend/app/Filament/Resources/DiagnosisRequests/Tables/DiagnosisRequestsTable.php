<?php

namespace App\Filament\Resources\DiagnosisRequests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiagnosisRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')
                    ->label('Scan ID')
                    ->searchable(),
                TextColumn::make('machine.name')
                    ->label('Machine')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('User')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('ai_detected_codes')
                    ->label('AI codes')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (filled($state) ? (string) $state : '-'))
                    ->searchable(query: fn ($query, string $search) => $query->whereJsonContains('ai_detected_codes', $search)),
                TextColumn::make('user_entered_codes')
                    ->label('Entered codes')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (filled($state) ? (string) $state : '-'))
                    ->searchable(query: fn ($query, string $search) => $query->whereJsonContains('user_entered_codes', $search)),
                TextColumn::make('selectedDiagnosticEntry.primary_code')
                    ->label('Resolved code')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('confidence')
                    ->numeric()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Scanned at'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
