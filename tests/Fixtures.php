<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder\Tests;

use Fylgja\PropsBuilder\PropsBuilder;

final class Fixtures
{
    public const SHADOW_UMBRA = 'hsl(var(--shadow-color) / calc(var(--shadow-weight) + 18%))';
    public const SHADOW_PENUMBRA = 'hsl(var(--shadow-color) / calc(var(--shadow-weight) + 12%))';
    public const SHADOW_AMBIENT = 'hsl(var(--shadow-color) / calc(var(--shadow-weight) + 10%))';

    public const PROPS_KEYS = [
        'spin-@' => '@keyframes spin { to { rotate: 360deg }}',
        'animateSpin' => 'spin 1s linear infinite',
        'rotate-@' => '@keyframes rotate { to { rotate: 360deg }}',
        'animateRotate' => 'rotate 1s linear infinite',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function props(): array
    {
        return [
            'layer-1' => 1,
            'size-2' => '0.5em',
            'size-4' => '4em',
            'color' => [
                // A scale keyed from 0 is a list in PHP, the object keeps it a map
                'red' => (object) [
                    '#fff5f5',
                    '#ffe3e3',
                    '#ffc9c9',
                    '#ffa8a8',
                    '#ff8787',
                    '#ff6b6b',
                    '#fa5252',
                    '#f03e3e',
                    '#e03131',
                    '#f00',
                ],
                'green' => '#0f0',
                'blue' => '#00f',
            ],
            'border' => [
                'size-2' => '0.5em',
                'size-4' => '4em',
            ],
            'shadow' => [
                'color' => 'gray',
                'weight' => '1%',
                1 => [
                    '0 2px 1px -1px ' . self::SHADOW_UMBRA,
                    '0 1px 1px ' . self::SHADOW_PENUMBRA,
                    '0 1px 3px ' . self::SHADOW_AMBIENT,
                ],
            ],
            'shadow-@media:dark' => [
                'color' => 'hsl(220 40% 2%)',
                'weight' => '25%',
            ],
            ...self::PROPS_KEYS,
        ];
    }

    /**
     * @return object
     */
    public static function designTokens(): object
    {
        return json_decode((string) file_get_contents(__DIR__ . '/fixtures/test.tokens.json'), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return string
     */
    public static function stitchMarkdown(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/test.stitch.md');
    }

    /**
     * Builds the output without writing it to a file.
     *
     * @param array<string|int, mixed>|object|string $props
     * @param string $filename
     * @param mixed ...$options - named `PropsBuilder::build()` options
     * @return string
     */
    public static function build(array|object|string $props, string $filename, mixed ...$options): string
    {
        return PropsBuilder::build($props, $filename, ...$options, writeToFile: false);
    }

    /**
     * @param array<string|int, mixed>|object|string $props
     * @param string $filename
     * @param mixed ...$options - named `PropsBuilder::build()` options
     * @return array<string|int, mixed>
     */
    public static function buildJson(array|object|string $props, string $filename, mixed ...$options): array
    {
        return json_decode(self::build($props, $filename, ...$options), true, 512, JSON_THROW_ON_ERROR);
    }
}
