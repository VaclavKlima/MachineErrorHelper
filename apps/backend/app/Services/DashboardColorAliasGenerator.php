<?php

namespace App\Services;

class DashboardColorAliasGenerator
{
    /**
     * @return array<int, string>
     */
    public function aliasesForHex(string $hex): array
    {
        $rgb = $this->rgbFromHex($hex);

        if ($rgb === null) {
            return [];
        }

        [$red, $green, $blue] = $rgb;
        $chroma = (max($red, $green, $blue) - min($red, $green, $blue)) / 255;
        [$hue, $saturation, $lightness] = $this->hsl($red, $green, $blue);

        $aliases = [];

        if ($saturation < 0.12 || $chroma < 0.08) {
            $base = match (true) {
                $lightness < 0.18 => 'black',
                $lightness < 0.38 => 'dark gray',
                $lightness > 0.88 => 'white',
                $lightness > 0.68 => 'light gray',
                default => 'gray',
            };

            $aliases[] = $base;

            if (str_contains($base, 'gray')) {
                $aliases[] = 'grey';
            }

            if ($base === 'white') {
                $aliases[] = 'light gray';
                $aliases[] = 'pale gray';
            }

            if ($base === 'black') {
                $aliases[] = 'dark gray';
            }

            return array_values(array_unique($aliases));
        }

        $base = $this->baseColor($hue);
        $aliases[] = $base;

        if ($lightness < 0.34) {
            $aliases[] = 'dark '.$base;
        } elseif ($lightness > 0.72) {
            $aliases[] = 'light '.$base;
        }

        if ($saturation > 0.65 && $lightness >= 0.35 && $lightness <= 0.7) {
            $aliases[] = 'bright '.$base;
            $aliases[] = 'strong '.$base;
        } elseif ($saturation < 0.32) {
            $aliases[] = 'muted '.$base;
        }

        foreach ($this->nearbyAliases($hue, $base) as $alias) {
            $aliases[] = $alias;
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function rgbFromHex(string $hex): ?array
    {
        $normalized = ltrim(trim($hex), '#');

        if (! preg_match('/^[0-9a-f]{6}$/i', $normalized)) {
            return null;
        }

        return [
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function hsl(int $red, int $green, int $blue): array
    {
        $r = $red / 255;
        $g = $green / 255;
        $b = $blue / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        $lightness = ($max + $min) / 2;

        if ($delta === 0.0) {
            return [0.0, 0.0, $lightness];
        }

        $saturation = $delta / (1 - abs(2 * $lightness - 1));

        $hue = match ($max) {
            $r => 60 * fmod((($g - $b) / $delta), 6),
            $g => 60 * ((($b - $r) / $delta) + 2),
            default => 60 * ((($r - $g) / $delta) + 4),
        };

        if ($hue < 0) {
            $hue += 360;
        }

        return [$hue, $saturation, $lightness];
    }

    private function baseColor(float $hue): string
    {
        return match (true) {
            $hue < 15 || $hue >= 345 => 'red',
            $hue < 45 => 'orange',
            $hue < 70 => 'yellow',
            $hue < 155 => 'green',
            $hue < 190 => 'cyan',
            $hue < 250 => 'blue',
            $hue < 290 => 'purple',
            $hue < 345 => 'pink',
        };
    }

    /**
     * @return array<int, string>
     */
    private function nearbyAliases(float $hue, string $base): array
    {
        return match ($base) {
            'orange' => $hue < 30 ? ['red-orange', 'amber'] : ['amber', 'yellow-orange'],
            'yellow' => ['amber', 'yellow-orange'],
            'cyan' => ['blue-green', 'teal'],
            'blue' => $hue < 215 ? ['sky blue'] : [],
            'purple' => ['violet'],
            'pink' => ['magenta'],
            default => [],
        };
    }
}
