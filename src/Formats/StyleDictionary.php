<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder\Formats;

final class StyleDictionary
{
    /**
     * Adds the right type to the design token value.
     *
     * @source https://styledictionary.com/info/tokens/
     * @param string $key
     * @param list<string> $colorKeys - possible color keys
     * @return array<string, string>
     */
    public static function meta(string $key, array $colorKeys = []): array
    {
        return match (true) {
            str_contains($key, 'size') && !str_contains($key, 'border-size') => ['type' => 'size'],
            str_contains($key, 'border-size') => ['type' => 'border-width'],
            str_contains($key, 'radius') => ['type' => 'border-radius'],
            str_contains($key, 'shadow') => ['type' => 'box-shadow'],
            Tokens::containsAny($key, $colorKeys) => ['type' => 'color'],
            default => [],
        };
    }
}
