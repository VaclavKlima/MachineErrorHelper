<?php

namespace App\Filament\Resources\DashboardColorMeanings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DashboardColorMeaningsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('machine.name')
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('hex_color')
                    ->label('Color'),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('ai_key')
                    ->label('AI key')
                    ->searchable(),
                TextColumn::make('ai_aliases')
                    ->label('AI aliases')
                    ->state(fn ($record): string => implode(', ', $record->ai_aliases ?? []))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
