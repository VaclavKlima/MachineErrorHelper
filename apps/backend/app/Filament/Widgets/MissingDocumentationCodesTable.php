<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DiagnosticEntries\DiagnosticEntryResource;
use App\Filament\RichContent\YouTubeEmbedBlock;
use App\Models\CodeDocumentation;
use App\Models\DiagnosticEntry;
use App\Services\DocumentationOpportunityService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class MissingDocumentationCodesTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Code usage';

    protected int|string|array $columnSpan = 'full';

    public string $viewMode = 'missing';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getUsageQuery())
            ->defaultSort('usage_count', 'desc')
            ->headerActions($this->getViewModeActions())
            ->columns([
                TextColumn::make('machine.name')
                    ->label('Machine')
                    ->searchable(),
                TextColumn::make('module_key')
                    ->label('Module')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('primary_code')
                    ->label('Code')
                    ->searchable(),
                TextColumn::make('title')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('code_documentations_count')
                    ->label('Docs')
                    ->badge()
                    ->sortable(),
                TextColumn::make('usage_count')
                    ->label('Scan hits')
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('machine_id')
                    ->relationship('machine', 'name')
                    ->label('Machine'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('editCode')
                        ->label('Edit code')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->url(fn (DiagnosticEntry $record): string => DiagnosticEntryResource::getUrl('edit', ['record' => $record])),
                    Action::make('createDocumentationForCode')
                        ->label('Create documentation for this code')
                        ->icon(Heroicon::OutlinedDocumentPlus)
                        ->schema([
                            TextInput::make('title')
                                ->required()
                                ->default(fn (DiagnosticEntry $record): string => $this->formatDocumentationTitle($record))
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
                        ->action(function (DiagnosticEntry $record, array $data): void {
                            $documentation = CodeDocumentation::query()->create([
                                'title' => $data['title'],
                                'content' => $data['content'],
                            ]);

                            $record->codeDocumentations()->syncWithoutDetaching([$documentation->getKey()]);
                        }),
                    Action::make('attachExistingDocumentation')
                        ->label('Attach existing documentation')
                        ->icon(Heroicon::OutlinedLink)
                        ->schema([
                            Select::make('documentation_ids')
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
                                ->searchable()
                                ->multiple()
                                ->required(),
                        ])
                        ->action(function (DiagnosticEntry $record, array $data): void {
                            $record->codeDocumentations()->syncWithoutDetaching($data['documentation_ids'] ?? []);
                        }),
                ])
                    ->label('Actions')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->tooltip('Actions')
                    ->dropdownWidth('xs'),
            ]);
    }

    protected function getUsageQuery(): Builder
    {
        $service = app(DocumentationOpportunityService::class);

        return match ($this->viewMode) {
            'documented' => $service->documentedCodesQuery(),
            'all' => $service->usedCodesQuery(),
            default => $service->missingDocumentationQuery(),
        };
    }

    /**
     * @return array<Action>
     */
    protected function getViewModeActions(): array
    {
        return [
            $this->makeViewModeAction(
                name: 'showMissingDocumentation',
                label: 'Missing docs',
                icon: Heroicon::OutlinedExclamationTriangle,
                viewMode: 'missing',
                badge: (string) app(DocumentationOpportunityService::class)->missingDocumentationCodesCount(),
            ),
            $this->makeViewModeAction(
                name: 'showAllCodeUsage',
                label: 'All used codes',
                icon: Heroicon::OutlinedChartBarSquare,
                viewMode: 'all',
                badge: (string) app(DocumentationOpportunityService::class)->usedCodesCount(),
            ),
            $this->makeViewModeAction(
                name: 'showDocumentedCodes',
                label: 'Documented only',
                icon: Heroicon::OutlinedDocumentText,
                viewMode: 'documented',
                badge: (string) app(DocumentationOpportunityService::class)->documentedUsedCodesCount(),
            ),
        ];
    }

    protected function makeViewModeAction(string $name, string $label, string|BackedEnum $icon, string $viewMode, string $badge): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->badge($badge)
            ->color(fn (): string => $this->viewMode === $viewMode ? 'primary' : 'gray')
            ->outlined(fn (): bool => $this->viewMode !== $viewMode)
            ->action(fn () => $this->viewMode = $viewMode);
    }

    protected function formatDocumentationTitle(DiagnosticEntry $record): string
    {
        return collect([$record->module_key, $record->primary_code])
            ->filter(fn (?string $value): bool => filled($value))
            ->implode(' - ');
    }
}
