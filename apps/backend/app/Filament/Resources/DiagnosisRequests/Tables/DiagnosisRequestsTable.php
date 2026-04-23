<?php

namespace App\Filament\Resources\DiagnosisRequests\Tables;

use App\Models\DiagnosisRequest;
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
                TextColumn::make('machine.name')
                    ->label('Machine')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('User')
                    ->searchable()
                    ->limit(28)
                    ->placeholder('-'),
                TextColumn::make('codes')
                    ->label('Codes')
                    ->state(fn (DiagnosisRequest $record): string => match (true) {
                        filled($record->user_entered_codes) => implode(', ', $record->user_entered_codes),
                        filled($record->ai_detected_codes) => implode(', ', $record->ai_detected_codes),
                        default => '-',
                    })
                    ->limit(28)
                    ->searchable(query: fn ($query, string $search) => $query
                        ->whereJsonContains('user_entered_codes', $search)
                        ->orWhereJsonContains('ai_detected_codes', $search)),
                TextColumn::make('selectedDiagnosticEntry.primary_code')
                    ->label('Resolved')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('State')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'resolved' => 'success',
                        'failed' => 'danger',
                        'processing' => 'warning',
                        'needs_confirmation' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'uploaded' => 'Uploaded',
                        'processing' => 'Processing',
                        'resolved' => 'Resolved',
                        'needs_confirmation' => 'Review',
                        'failed' => 'Failed',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Scanned'),
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
