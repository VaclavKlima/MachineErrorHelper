<?php

namespace App\Filament\Resources\Manuals\RelationManagers;

use App\Filament\Resources\DiagnosticEntries\DiagnosticEntryResource;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ErrorCodesRelationManager extends RelationManager
{
    protected static string $relationship = 'diagnosticEntries';

    protected static ?string $relatedResource = DiagnosticEntryResource::class;

    protected static ?string $title = 'Error Codes';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
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
                    }),
                TextColumn::make('module_key')
                    ->label('Module')
                    ->searchable(),
                TextColumn::make('primary_code')
                    ->label('Code')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
