<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder\Formats;

final class Tokens
{
    /**
     * Adds the right type to the design token value.
     *
     * @source https://www.designtokens.org/tr/drafts/format/#types
     * @param string $key
     * @param list<string> $colorKeys - possible color keys
     * @return array<string, string>
     */
    public static function meta(string $key, array $colorKeys = []): array
    {
        return match (true) {
            str_contains($key, 'size') => ['$type' => 'dimension'],
            str_contains($key, 'family') => ['$type' => 'font-family'],
            str_contains($key, 'weight') => ['$type' => 'font-weight'],
            str_contains($key, 'duration') => ['$type' => 'duration'],
            str_contains($key, 'ease') => ['$type' => 'cubic-bezier'],
            self::containsAny($key, $colorKeys) => ['$type' => 'color'],
            self::containsAny($key, ['layer', 'lineHeight', 'line-height']) => ['$type' => 'number'],
            default => [],
        };
    }

    /**
     * @param string $key
     * @param list<string> $needles
     * @return bool
     */
    public static function containsAny(string $key, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
