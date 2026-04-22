<?php

namespace App\Filament\Resources\ManualExtractionCandidates\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
                        'published' => 'success',
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
                    ->label('High-confidence extracted codes')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', 'published')
                        ->where('review_priority', 'high')
                        ->where('review_score', '>=', 0.78)),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending import',
                        'published' => 'Published',
                        'rejected' => 'Rejected',
                        'ignored' => 'Ignored',
                    ])
                    ->default('published'),
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
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
