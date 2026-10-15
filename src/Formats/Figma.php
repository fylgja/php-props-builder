<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder\Formats;

final class Figma
{
    /**
     * Adds the right type to the design token value.
     *
     * @param string $key
     * @param list<string> $colorKeys - possible color keys
     * @return array<string, string>
     */
    public static function meta(string $key, array $colorKeys = []): array
    {
        return match (true) {
            str_contains($key, 'size') && !str_contains($key, 'border-size') => ['type' => 'sizing'],
            str_contains($key, 'border-size') => ['type' => 'borderWidth'],
            str_contains($key, 'radius') => ['type' => 'borderRadius'],
            str_contains($key, 'shadow') => ['type' => 'boxShadow'],
            Tokens::containsAny($key, $colorKeys) => ['type' => 'color'],
            default => ['type' => 'other'],
        };
    }
}
