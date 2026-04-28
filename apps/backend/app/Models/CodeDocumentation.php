<?php

namespace App\Models;

use App\Filament\RichContent\YouTubeEmbedBlock;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CodeDocumentation extends Model implements HasRichContent
{
    use HasFactory;
    use InteractsWithRichContent;

    protected $fillable = [
        'title',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    protected function setUpRichContent(): void
    {
        $this->registerRichContent('content')
            ->customBlocks([
                YouTubeEmbedBlock::class,
            ])
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public')
            ->json();
    }

    public function diagnosticEntries(): BelongsToMany
    {
        return $this->belongsToMany(DiagnosticEntry::class, 'code_documentation_diagnostic_entry')
            ->withTimestamps();
    }
}
