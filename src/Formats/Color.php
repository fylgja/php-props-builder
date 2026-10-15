<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder\Formats;

use Fylgja\PropsBuilder\Utils;
use Fylgja\PropsBuilder\ValueList;

final class Color
{
    /**
     * Color spaces that CSS writes with the `color()` function,
     * every other supported color space has its own function (e.g. `oklch()`).
     *
     * @source https://www.designtokens.org/tr/2025.10/color/#supported-color-spaces
     */
    private const COLOR_FUNCTION_SPACES = [
        'srgb',
        'srgb-linear',
        'display-p3',
        'a98-rgb',
        'prophoto-rgb',
        'rec2020',
        'xyz-d65',
        'xyz-d50',
    ];

    /**
     * Per channel `[percentScale, numberScale]`, mapping a CSS channel to the
     * Design Token component range. A `null` percentScale means the channel
     * is a hue, which does not accept percentages.
     *
     * @var array<string, list<array{float|null, float}>>
     */
    private const CHANNEL_SCALES = [
        'rgb' => [[0.01, 1 / 255], [0.01, 1 / 255], [0.01, 1 / 255]],
        'hsl' => [[null, 1], [1, 1], [1, 1]],
        'hwb' => [[null, 1], [1, 1], [1, 1]],
        'lab' => [[1, 1], [1.25, 1], [1.25, 1]],
        'lch' => [[1, 1], [1.5, 1], [null, 1]],
        'oklab' => [[0.01, 1], [0.004, 1], [0.004, 1]],
        'oklch' => [[0.01, 1], [0.004, 1], [null, 1]],
        'color' => [[0.01, 1], [0.01, 1], [0.01, 1]],
    ];

    /** CSS functions whose Design Token components are percentages */
    private const PERCENT_COMPONENT_SPACES = ['hsl', 'hwb'];

    private const NUMBER_PATTERN = '-?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?';
    private const HEX_REGEX = '/^#([\da-f]{3,4}|[\da-f]{6}|[\da-f]{8})$/i';
    private const COLOR_FUNCTION_REGEX = '/^(rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\(\s*([^()]*)\)$/i';

    /**
     * Removes floating point noise, e.g. `0.1 * 3` becoming `0.30000000000000004`.
     *
     * @param float|int $value
     * @return float|int
     */
    public static function round(float|int $value): float|int
    {
        $rounded = (float) sprintf('%.12g', $value);

        return floor($rounded) === $rounded && abs($rounded) < PHP_INT_MAX ? (int) $rounded : $rounded;
    }

    /**
     * @param string $channel - CSS channel, e.g. `50%`, `.3`, `120deg` or `none`
     * @param array{float|int|null, float|int} $scale
     * @return float|int|string|null - `null` when the channel can't be parsed
     */
    private static function parseChannel(string $channel, array $scale): float|int|string|null
    {
        if ($channel === 'none') {
            return 'none';
        }

        if (!preg_match('/^(' . self::NUMBER_PATTERN . ')(%|deg)?$/i', $channel, $match)) {
            return null;
        }

        [$percentScale, $numberScale] = $scale;
        $value = (float) $match[1];

        if (($match[2] ?? '') === '%') {
            return $percentScale === null ? null : self::round($value * $percentScale);
        }

        return self::round($value * $numberScale);
    }

    /**
     * @param list<mixed> $components - sRGB components in the 0 to 1 range
     * @return string|null
     */
    private static function componentsToHex(array $components): ?string
    {
        $hex = '#';

        foreach ($components as $component) {
            if (!is_int($component) && !is_float($component) || $component < 0 || $component > 1) {
                return null;
            }
            $hex .= str_pad(dechex((int) round($component * 255)), 2, '0', STR_PAD_LEFT);
        }

        return $hex;
    }

    /**
     * @param string $hex - hex digits without the leading `#`
     * @return array<string, mixed>
     */
    private static function hexToColor(string $hex): array
    {
        $digits = strlen($hex) <= 4
            ? array_map(static fn (string $digit): string => $digit . $digit, str_split($hex))
            : str_split($hex, 2);
        $channels = array_map(static fn (string $digit): float => hexdec($digit) / 255, $digits);
        $components = array_map([self::class, 'round'], array_slice($channels, 0, 3));
        $color = ['colorSpace' => 'srgb', 'components' => new ValueList($components)];
        $alpha = $channels[3] ?? null;

        if ($alpha !== null && $alpha < 1) {
            $color['alpha'] = self::round($alpha);
        }
        $color['hex'] = self::componentsToHex($components);

        return $color;
    }

    /**
     * Parses a CSS color into a Design Token color value.
     * Only literal colors are supported, a color using `var()` or `calc()` returns `null`.
     *
     * @param mixed $input - CSS color, e.g. `#f00`, `hsl(0 0 0 / 10%)` or `oklch(70% .1 200)`
     * @return array<string, mixed>|null
     */
    public static function parse(mixed $input): ?array
    {
        if (!is_string($input)) {
            return null;
        }

        $value = strtolower(trim($input));

        if (preg_match(self::HEX_REGEX, $value, $hex)) {
            return self::hexToColor($hex[1]);
        }

        if (!preg_match(self::COLOR_FUNCTION_REGEX, $value, $colorFunction)) {
            return null;
        }

        $name = preg_replace('/^(rgb|hsl)a$/', '$1', $colorFunction[1]);
        $parts = explode('/', str_replace(',', ' ', $colorFunction[2]));
        $channels = preg_split('/\s+/', trim($parts[0]));
        $alphaChannel = isset($parts[1]) ? trim($parts[1]) : null;
        $colorSpace = $name === 'rgb' ? 'srgb' : $name;

        if ($name === 'color') {
            $colorSpace = array_shift($channels);
            if (!in_array($colorSpace, self::COLOR_FUNCTION_SPACES, true)) {
                return null;
            }
        }

        // Legacy syntax, e.g. `rgba(0, 0, 0, 0.5)`
        if (count($channels) === 4 && $alphaChannel === null) {
            $alphaChannel = array_pop($channels);
        }

        if (count($channels) !== 3) {
            return null;
        }

        $components = [];
        foreach ($channels as $index => $channel) {
            $component = self::parseChannel($channel, self::CHANNEL_SCALES[$name][$index]);
            if ($component === null) {
                return null;
            }
            $components[] = $component;
        }

        $color = ['colorSpace' => $colorSpace, 'components' => new ValueList($components)];

        if ($alphaChannel !== null) {
            $alpha = self::parseChannel($alphaChannel, [0.01, 1]);
            if (!is_int($alpha) && !is_float($alpha)) {
                return null;
            }
            if ($alpha < 1) {
                $color['alpha'] = $alpha;
            }
        }

        if ($colorSpace === 'srgb') {
            $hexValue = self::componentsToHex($components);
            if ($hexValue !== null) {
                $color['hex'] = $hexValue;
            }
        }

        return $color;
    }

    /**
     * Converts a Design Token color value into a CSS color.
     *
     * @param array<string, mixed> $color
     * @param callable(mixed, string|null=): mixed $toCss - converts a nested value, e.g. a component `$ref`
     * @return string
     */
    public static function toCss(array $color, callable $toCss): string
    {
        $colorSpace = Utils::toString($color['colorSpace'] ?? '');
        $components = $color['components'] ?? new ValueList([]);
        $alphaValue = array_key_exists('alpha', $color) ? $toCss($color['alpha'], 'number') : 1;
        $hasAlpha = !is_int($alphaValue) && !is_float($alphaValue) || $alphaValue != 1;
        $hex = $color['hex'] ?? null;

        if ($colorSpace === 'srgb' && $hex && !$hasAlpha) {
            return $hex;
        }

        $channels = [];
        foreach ($components instanceof ValueList ? $components->items : [] as $index => $component) {
            $channel = $toCss($component, 'number');
            // The first component of hsl and hwb is the hue, which has no percentage
            $isPercent = $index > 0
                && in_array($colorSpace, self::PERCENT_COMPONENT_SPACES, true)
                && (is_int($channel) || is_float($channel));

            $channels[] = Utils::toString($channel) . ($isPercent ? '%' : '');
        }

        $channels = implode(' ', $channels);
        $alphaChannel = $hasAlpha ? ' / ' . Utils::toString($alphaValue) : '';

        return in_array($colorSpace, self::COLOR_FUNCTION_SPACES, true)
            ? "color({$colorSpace} {$channels}{$alphaChannel})"
            : "{$colorSpace}({$channels}{$alphaChannel})";
    }
}
