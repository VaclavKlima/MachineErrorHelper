<?php

namespace App\Filament\Resources\Manuals\Tables;

use App\Jobs\ProcessManualImport;
use App\Models\Manual;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManualsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('machine.name')
                    ->label('Machine')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Manual')
                    ->searchable(),
                TextColumn::make('coverage_mode')
                    ->label('Coverage')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'complete' => 'Complete',
                        'delta' => 'Delta',
                        'supplement' => 'Supplement',
                        default => ucfirst($state),
                    }),
                TextColumn::make('language')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cs' => 'CZ',
                        'en' => 'EN',
                        default => strtoupper($state),
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'extracted' => 'success',
                        'failed' => 'danger',
                        'processing' => 'warning',
                        'queued' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Uploaded'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('extract')
                    ->label('Extract codes')
                    ->requiresConfirmation()
                    ->disabled(fn (Manual $record): bool => in_array($record->status, ['queued', 'processing'], true))
                    ->action(function (Manual $record): void {
                        if (in_array($record->status, ['queued', 'processing'], true)) {
                            Notification::make()
                                ->warning()
                                ->title('Extraction already running')
                                ->send();

                            return;
                        }

                        $record->forceFill(['status' => 'queued'])->save();

                        ProcessManualImport::dispatch($record->id);

                        Notification::make()
                            ->success()
                            ->title('Extraction queued')
                            ->body('The manual will be processed in the background. Refresh this table or check Horizon for progress.')
                            ->send();
                    }),
                Action::make('requeueExtraction')
                    ->label('Requeue extraction')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Manual $record): bool => in_array($record->status, ['queued', 'processing'], true))
                    ->action(function (Manual $record): void {
                        $record->forceFill(['status' => 'queued'])->save();

                        ProcessManualImport::dispatch($record->id);

                        Notification::make()
                            ->success()
                            ->title('Extraction requeued')
                            ->body('The manual import job was queued again. A lock prevents two imports for this manual from running at the same time.')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
