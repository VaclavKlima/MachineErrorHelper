<?php

namespace App\Filament\Resources\ErrorCodeDefinitions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ErrorCodeDefinitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('errorCode.id')
                    ->searchable(),
                TextColumn::make('manual.title')
                    ->searchable(),
                TextColumn::make('manualChunk.id')
                    ->searchable(),
                TextColumn::make('effective_from_version_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('effective_to_version_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('supersedes_definition_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('source_page_number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('severity')
                    ->searchable(),
                TextColumn::make('source_confidence')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('approval_status')
                    ->searchable(),
                TextColumn::make('approved_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->dateTime()
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
