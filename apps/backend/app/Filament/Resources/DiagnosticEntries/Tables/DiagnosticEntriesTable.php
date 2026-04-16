<?php

namespace App\Filament\Resources\DiagnosticEntries\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DiagnosticEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('machine.name')
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
                TextColumn::make('meaning')
                    ->searchable()
                    ->limit(80),
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
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'approved' => 'Approved',
                        'draft' => 'Draft',
                        'archived' => 'Archived',
                    ]),
                SelectFilter::make('machine_id')
                    ->relationship('machine', 'name')
                    ->label('Machine'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
