<?php

namespace App\Filament\Resources\CodeDocumentations\Schemas;

use App\Models\DiagnosticEntry;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CodeDocumentationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('diagnosticEntries')
                    ->label('Linked codes')
                    ->relationship(
                        name: 'diagnosticEntries',
                        titleAttribute: 'primary_code',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->with('machine:id,name')
                            ->orderBy('primary_code_normalized')
                            ->orderBy('primary_code')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (DiagnosticEntry $record): string => self::formatDiagnosticEntryLabel($record)
                    )
                    ->getOptionLabelsUsing(
                        fn (array $values): array => DiagnosticEntry::query()
                            ->with('machine:id,name')
                            ->whereKey($values)
                            ->get()
                            ->mapWithKeys(fn (DiagnosticEntry $record): array => [
                                $record->getKey() => self::formatDiagnosticEntryLabel($record),
                            ])
                            ->all()
                    )
                    ->getSearchResultsUsing(
                        fn (string $search): array => DiagnosticEntry::query()
                            ->with('machine:id,name')
                            ->searchForDocumentation($search)
                            ->orderBy('primary_code_normalized')
                            ->orderBy('primary_code')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (DiagnosticEntry $record): array => [
                                $record->getKey() => self::formatDiagnosticEntryLabel($record),
                            ])
                            ->all()
                    )
                    ->helperText('Search by code, machine, module, or title. Multiple terms can match across different fields.')
                    ->searchable()
                    ->multiple()
                    ->required()
                    ->columnSpanFull(),
                RichEditor::make('content')
                    ->label('Documentation body')
                    ->json()
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('documentation-images')
                    ->fileAttachmentsVisibility('public')
                    ->fileAttachmentsAcceptedFileTypes([
                        'image/png',
                        'image/jpeg',
                        'image/gif',
                        'image/webp',
                        'image/svg+xml',
                    ])
                    ->fileAttachmentsMaxSize(20480)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    private static function formatDiagnosticEntryLabel(DiagnosticEntry $record): string
    {
        return $record->documentation_label;
    }
}
