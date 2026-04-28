<?php

namespace App\Filament\RichContent;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;

class YouTubeEmbedBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'youtubeEmbed';
    }

    public static function getLabel(): string
    {
        return 'YouTube video';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([
            TextInput::make('title')
                ->label('Title')
                ->maxLength(255),
            TextInput::make('url')
                ->label('YouTube URL')
                ->url()
                ->required()
                ->helperText('Paste a youtube.com or youtu.be link. The video is stored inside the documentation body.'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        return filled($config['title'] ?? null)
            ? (string) $config['title']
            : 'YouTube video';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function toPreviewHtml(array $config): ?string
    {
        $label = e(static::getPreviewLabel($config));
        $url = e((string) ($config['url'] ?? ''));

        return <<<HTML
            <div style="border:1px solid rgba(148,163,184,.35);border-radius:8px;padding:12px;background:rgba(15,23,42,.04);">
                <strong>{$label}</strong>
                <div style="font-size:12px;color:rgb(100,116,139);margin-top:4px;">{$url}</div>
            </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    public static function toHtml(array $config, array $data): ?string
    {
        $videoId = static::extractVideoId((string) ($config['url'] ?? ''));

        if (! $videoId) {
            return null;
        }

        $title = e((string) ($config['title'] ?? 'YouTube video'));
        $embedUrl = 'https://www.youtube-nocookie.com/embed/'.$videoId;

        return <<<HTML
            <figure class="not-prose" style="margin:1rem 0;">
                <iframe
                    src="{$embedUrl}"
                    title="{$title}"
                    style="aspect-ratio:16/9;width:100%;border:0;border-radius:8px;"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen
                ></iframe>
                <figcaption style="font-size:.875rem;margin-top:.5rem;">{$title}</figcaption>
            </figure>
        HTML;
    }

    private static function extractVideoId(string $url): ?string
    {
        if (preg_match('/youtu\.be\/([A-Za-z0-9_-]{6,})/i', $url, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/[?&]v=([A-Za-z0-9_-]{6,})/i', $url, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/youtube\.com\/embed\/([A-Za-z0-9_-]{6,})/i', $url, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/youtube\.com\/shorts\/([A-Za-z0-9_-]{6,})/i', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
