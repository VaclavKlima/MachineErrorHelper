<?php

namespace App\Filament\Resources\DiagnosticEntries\Schemas;

use App\Filament\RichContent\YouTubeEmbedBlock;
use App\Models\CodeDocumentation;
use App\Models\DiagnosticEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DiagnosticEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('machine_id')
                    ->relationship('machine', 'name')
                    ->required(),
                Select::make('status')
                    ->label('State')
                    ->options([
                        'active' => 'Active',
                        'disabled' => 'Disabled',
                    ])
                    ->required()
                    ->default('active'),
                TextInput::make('module_key')
                    ->label('Module / context key')
                    ->maxLength(255),
                TextInput::make('primary_code')
                    ->maxLength(255),
                TextInput::make('primary_code_normalized')
                    ->maxLength(255),
                Select::make('codeDocumentations')
                    ->label('Documentation')
                    ->options(
                        fn (): array => CodeDocumentation::query()
                            ->orderBy('title')
                            ->pluck('title', 'id')
                            ->all()
                    )
                    ->getSearchResultsUsing(
                        fn (string $search): array => CodeDocumentation::query()
                            ->where('title', 'like', "%{$search}%")
                            ->orderBy('title')
                            ->limit(50)
                            ->pluck('title', 'id')
                            ->all()
                    )
                    ->getOptionLabelsUsing(
                        fn (array $values): array => CodeDocumentation::query()
                            ->whereKey($values)
                            ->pluck('title', 'id')
                            ->all()
                    )
                    ->createOptionForm([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        RichEditor::make('content')
                            ->label('Documentation body')
                            ->json()
                            ->customBlocks([
                                YouTubeEmbedBlock::class,
                            ])
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
                    ])
                    ->createOptionAction(
                        fn (Action $action, Select $component): Action => $action->fillForm([
                            'title' => self::formatDocumentationTitle(
                                moduleKey: $component->evaluate(fn ($get) => $get('module_key')),
                                primaryCode: $component->evaluate(fn ($get) => $get('primary_code')),
                            ),
                        ])
                    )
                    ->createOptionUsing(
                        fn (array $data): int => CodeDocumentation::query()->create($data)->getKey()
                    )
                    ->loadStateFromRelationshipsUsing(
                        function (Select $component): void {
                            $component->state(
                                $component->getRecord()->codeDocumentations()->pluck('code_documentations.id')->all()
                            );
                        }
                    )
                    ->saveRelationshipsUsing(
                        function (Select $component, DiagnosticEntry $record, ?array $state): void {
                            $record->codeDocumentations()->sync($state ?? []);
                        }
                    )
                    ->searchable()
                    ->multiple()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                TextInput::make('section_title')
                    ->maxLength(255)
                    ->columnSpanFull(),
                KeyValue::make('identifiers')
                    ->keyLabel('Identifier')
                    ->valueLabel('Value')
                    ->required()
                    ->columnSpanFull(),
                KeyValue::make('context')
                    ->keyLabel('Context')
                    ->valueLabel('Value')
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('meaning')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('cause')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('recommended_action')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('source_text')
                    ->rows(5)
                    ->columnSpanFull(),
                TextInput::make('source_page_number')
                    ->numeric(),
            ]);
    }

    private static function formatDocumentationTitle(?string $moduleKey, ?string $primaryCode): string
    {
        return collect([$moduleKey, $primaryCode])
            ->filter(fn (?string $value): bool => filled($value))
            ->implode(' - ');
    }
}
