<?php

namespace App\Filament\Resources\ManualExtractionCandidates\Tables;

use App\Models\ManualExtractionCandidate;
use App\Services\ManualExtractionCandidateApprovalService;
use App\Services\ManualExtractionCandidateAutoApprovalService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class ManualExtractionCandidatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('review_score', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'ignored' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('review_priority')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'high' => 'danger',
                        'normal' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('module_key')
                    ->label('Module')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(70),
                TextColumn::make('manual.title')
                    ->label('Manual')
                    ->searchable()
                    ->limit(35),
                TextColumn::make('source_page_number')
                    ->label('Page')
                    ->sortable(),
                TextColumn::make('confidence')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('review_score')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('noise_reason')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extractor')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('high_confidence_queue')
                    ->label('High-confidence queue')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', 'pending')
                        ->where('review_priority', 'high')
                        ->where('review_score', '>=', 0.78)),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'ignored' => 'Ignored',
                    ])
                    ->default('pending'),
                SelectFilter::make('review_priority')
                    ->label('Priority')
                    ->options([
                        'high' => 'High',
                        'normal' => 'Normal',
                        'low' => 'Low',
                    ]),
                SelectFilter::make('extractor')
                    ->options([
                        'generic_diagnostic_block' => 'Diagnostic block',
                        'generic_section_table' => 'Table parser',
                        'generic_text_reference' => 'Text parser',
                        'gemini_structured_chunk' => 'Gemini',
                    ]),
                SelectFilter::make('manual_id')
                    ->relationship('manual', 'title')
                    ->label('Manual'),
            ])
            ->headerActions([
                Action::make('autoApproveHighConfidence')
                    ->label('Approve high-confidence')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve high-confidence suggestions?')
                    ->modalDescription(fn (ManualExtractionCandidateAutoApprovalService $service): string => sprintf(
                        'This will approve %d pending high-confidence suggestions across all manuals and publish them to Diagnostic knowledge.',
                        $service->countForManual(),
                    ))
                    ->visible(fn (ManualExtractionCandidateAutoApprovalService $service): bool => $service->countForManual() > 0)
                    ->action(function (ManualExtractionCandidateAutoApprovalService $service): void {
                        $approved = $service->approveForManual(null, auth()->user());

                        Notification::make()
                            ->success()
                            ->title('Diagnostic knowledge updated')
                            ->body("Approved {$approved} high-confidence suggestions.")
                            ->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ManualExtractionCandidate $record): bool => $record->status !== 'approved')
                    ->action(function (ManualExtractionCandidate $record, ManualExtractionCandidateApprovalService $service): void {
                        $service->approve($record, auth()->user());

                        Notification::make()
                            ->success()
                            ->title('Suggestion approved')
                            ->body('The diagnostic entry is now available to the diagnosis flow.')
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ManualExtractionCandidate $record): bool => $record->status !== 'rejected')
                    ->action(function (ManualExtractionCandidate $record, ManualExtractionCandidateApprovalService $service): void {
                        $service->reject($record, auth()->user());

                        Notification::make()
                            ->success()
                            ->title('Suggestion rejected')
                            ->send();
                    }),
                Action::make('ignore')
                    ->label('Ignore')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (ManualExtractionCandidate $record): bool => $record->status !== 'ignored')
                    ->action(function (ManualExtractionCandidate $record): void {
                        $record->forceFill([
                            'status' => 'ignored',
                            'noise_reason' => $record->noise_reason ?: 'manual_ignore',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ])->save();

                        Notification::make()
                            ->success()
                            ->title('Suggestion ignored')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('approveSelected')
                    ->label('Approve selected')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Collection $records, ManualExtractionCandidateApprovalService $service): void {
                        $approved = 0;

                        foreach ($records as $record) {
                            if ($record->status === 'approved') {
                                continue;
                            }

                            $service->approve($record, auth()->user());
                            $approved++;
                        }

                        Notification::make()
                            ->success()
                            ->title('Selected suggestions approved')
                            ->body("Approved {$approved} suggestions.")
                            ->send();
                    }),
            ]);
    }
}
