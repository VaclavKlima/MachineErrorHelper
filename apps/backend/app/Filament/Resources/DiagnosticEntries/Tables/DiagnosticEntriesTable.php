<?php

namespace App\Filament\Resources\DiagnosticEntries\Tables;

use App\Models\DiagnosticEntry;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class DiagnosticEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->label('State')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'disabled' => 'gray',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Active',
                        'disabled' => 'Disabled',
                        default => ucfirst($state),
                    }),
                TextColumn::make('machine.name')
                    ->label('Machine')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('module_key')
                    ->label('Module')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('primary_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(35),
                TextColumn::make('manual.title')
                    ->label('Manual')
                    ->searchable()
                    ->placeholder('-')
                    ->limit(24),
                TextColumn::make('code_documentations_count')
                    ->label('Docs')
                    ->counts('codeDocumentations')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('State')
                    ->options([
                        'active' => 'Active',
                        'disabled' => 'Disabled',
                    ]),
                SelectFilter::make('machine_id')
                    ->relationship('machine', 'name')
                    ->label('Machine'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('disable')
                        ->label('Disable')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (DiagnosticEntry $record): bool => ! $record->trashed() && $record->status !== 'disabled')
                        ->action(fn (DiagnosticEntry $record): bool => $record->forceFill(['status' => 'disabled'])->save()),
                    Action::make('enable')
                        ->label('Enable')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (DiagnosticEntry $record): bool => ! $record->trashed() && $record->status !== 'active')
                        ->action(fn (DiagnosticEntry $record): bool => $record->forceFill(['status' => 'active'])->save()),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('enableSelected')
                        ->label('Enable selected')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each(
                            fn (DiagnosticEntry $record): bool => $record->forceFill(['status' => 'active'])->save()
                        )),
                    BulkAction::make('disableSelected')
                        ->label('Disable selected')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(
                            fn (DiagnosticEntry $record): bool => $record->forceFill(['status' => 'disabled'])->save()
                        )),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
