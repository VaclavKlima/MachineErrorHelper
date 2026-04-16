<?php

namespace App\Filament\Resources\Manuals\Tables;

use App\Jobs\ProcessManualImport;
use App\Models\Manual;
use App\Services\ManualExtractionCandidateAutoApprovalService;
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
            ->columns([
                TextColumn::make('machine.name')
                    ->searchable(),
                TextColumn::make('softwareVersion.id')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('coverage_mode')
                    ->searchable(),
                TextColumn::make('language')
                    ->searchable(),
                TextColumn::make('file_path')
                    ->searchable(),
                TextColumn::make('file_hash')
                    ->searchable(),
                TextColumn::make('page_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('pages_count')
                    ->counts('pages')
                    ->label('Extracted pages')
                    ->sortable(),
                TextColumn::make('extraction_candidates_count')
                    ->counts('extractionCandidates')
                    ->label('Suggestions')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Action::make('autoApproveSuggestions')
                    ->label('Approve high-confidence')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve high-confidence suggestions?')
                    ->modalDescription(fn (Manual $record, ManualExtractionCandidateAutoApprovalService $service): string => sprintf(
                        'This will approve %d pending high-confidence suggestions for this manual and publish them to Diagnostic knowledge.',
                        $service->countForManual($record),
                    ))
                    ->visible(fn (Manual $record, ManualExtractionCandidateAutoApprovalService $service): bool => $service->countForManual($record) > 0)
                    ->action(function (Manual $record, ManualExtractionCandidateAutoApprovalService $service): void {
                        $approved = $service->approveForManual($record, auth()->user());

                        Notification::make()
                            ->success()
                            ->title('Diagnostic knowledge updated')
                            ->body("Approved {$approved} high-confidence suggestions.")
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
