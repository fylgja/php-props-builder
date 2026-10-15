<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder\Formats;

use Fylgja\PropsBuilder\Utils;
use Fylgja\PropsBuilder\ValueList;

/**
 * Converts between CSS values and the Design Tokens Format Module 2025.10 values.
 *
 * @source https://www.designtokens.org/tr/2025.10/format/
 */
final class Dtcg
{
    public const VERSION = '2025.10';

    public const REFERENCE_REGEX = '/\{([\w$.-]+)\}/';

    /** @source https://www.designtokens.org/tr/2025.10/format/#font-weight */
    private const FONT_WEIGHT_ALIASES = [
        'thin' => 100,
        'hairline' => 100,
        'extra-light' => 200,
        'ultra-light' => 200,
        'light' => 300,
        'normal' => 400,
        'regular' => 400,
        'book' => 400,
        'medium' => 500,
        'semi-bold' => 600,
        'demi-bold' => 600,
        'bold' => 700,
        'extra-bold' => 800,
        'ultra-bold' => 800,
        'black' => 900,
        'heavy' => 900,
        'extra-black' => 950,
        'ultra-black' => 950,
    ];

    private const TYPOGRAPHY_TYPES = [
        'fontFamily' => 'fontFamily',
        'fontSize' => 'dimension',
        'fontWeight' => 'fontWeight',
        'letterSpacing' => 'dimension',
        'lineHeight' => 'number',
    ];

    private const NUMBER_PATTERN = '-?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?';
    private const CSS_VAR_REGEX = '/^var\(--([\w-]+)\)$/';

    /**
     * @param mixed $node
     * @return bool
     */
    public static function isToken(mixed $node): bool
    {
        return is_array($node) && array_key_exists('$value', $node);
    }

    /**
     * Deep merges two token trees, where a token in `$override` replaces the token in `$base` as a whole.
     *
     * @param array<string|int, mixed> $base
     * @param mixed $override
     * @return array<string|int, mixed>
     */
    public static function mergeTokenTrees(array $base, mixed $override): array
    {
        $result = $base;

        foreach (is_array($override) ? $override : [] as $key => $value) {
            $current = $result[$key] ?? null;
            $canMerge = is_array($current) && !self::isToken($current)
                && is_array($value) && !self::isToken($value);

            $result[$key] = $canMerge ? self::mergeTokenTrees($current, $value) : $value;
        }

        return $result;
    }

    /**
     * Turns a token path into a CSS variable, dropping the `$root` segment,
     * the same way `DEFAULT` keys are dropped from the CSS output.
     *
     * @param list<string|int> $path - e.g. `['color', 'brand', '$root']`
     * @return string - e.g. `var(--color-brand)`
     */
    public static function pathToCssVar(array $path): string
    {
        $segments = array_filter($path, static fn ($segment): bool => $segment !== '$root' && $segment !== '$value');

        return 'var(--' . Utils::kebabCase(implode('-', $segments)) . ')';
    }

    /**
     * Replaces all curly brace references (e.g. `{color.brand.primary}`) with CSS variables.
     *
     * @param string $value
     * @return string
     */
    public static function referencesToCssVars(string $value): string
    {
        return preg_replace_callback(
            self::REFERENCE_REGEX,
            // `.value` is the legacy (Figma) way to point at a token value
            static fn (array $match): string => self::pathToCssVar(
                explode('.', preg_replace('/\.value$/', '', $match[1])),
            ),
            $value,
        );
    }

    /**
     * Guesses the token type from the shape of a value, for tokens without a (inherited) `$type`.
     *
     * @param mixed $value
     * @return string|null
     */
    private static function inferType(mixed $value): ?string
    {
        if ($value instanceof ValueList) {
            $items = $value->items;
            $hasItemWith = static fn (string $key): bool => (bool) array_filter(
                $items,
                static fn ($item): bool => is_array($item) && array_key_exists($key, $item),
            );

            return match (true) {
                count($items) === 4 && !array_filter($items, static fn ($item): bool => !is_int($item) && !is_float($item)) => 'cubicBezier',
                $hasItemWith('position') => 'gradient',
                $hasItemWith('offsetX') => 'shadow',
                default => null,
            };
        }

        if (!is_array($value)) {
            return null;
        }

        return match (true) {
            array_key_exists('colorSpace', $value) => 'color',
            array_key_exists('value', $value) && array_key_exists('unit', $value) => in_array($value['unit'], ['ms', 's'], true) ? 'duration' : 'dimension',
            array_key_exists('offsetX', $value) => 'shadow',
            array_key_exists('timingFunction', $value) => 'transition',
            array_key_exists('fontFamily', $value), array_key_exists('fontSize', $value) => 'typography',
            array_key_exists('width', $value) && array_key_exists('style', $value) => 'border',
            array_key_exists('dashArray', $value) => 'strokeStyle',
            default => null,
        };
    }

    /**
     * @param string $family
     * @return string
     */
    private static function quoteFontFamily(string $family): string
    {
        $needsQuotes = preg_match('/\s/', $family) && !preg_match('/^["\']|^var\(|,/', $family);

        return $needsQuotes ? "\"{$family}\"" : $family;
    }

    /**
     * Whether a value is a map of parts, and not a `$ref` to one.
     *
     * @param mixed $value
     * @return bool
     */
    private static function isComposite(mixed $value): bool
    {
        return is_array($value) && !isset($value['$ref']);
    }

    /**
     * Whether a value has nothing left to convert, which would otherwise recurse forever.
     *
     * @param mixed $value
     * @return bool
     */
    private static function isScalar(mixed $value): bool
    {
        return !is_array($value) && !is_string($value) && !$value instanceof ValueList;
    }

    /**
     * Joins CSS parts with a space, leaving out the empty ones.
     *
     * @param list<mixed> $parts
     * @return string
     */
    private static function joinParts(array $parts): string
    {
        $parts = array_map([Utils::class, 'toString'], $parts);

        return implode(' ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * Converts a 2025.10 token value into a CSS value.
     * Legacy string values are kept as is, only their references are converted.
     *
     * @param mixed $value - the `$value` of a token, or a part of it
     * @param string|null $type - the (inherited) `$type` of the token
     * @param callable(string): mixed $resolvePointer - resolves a `$ref` JSON Pointer into a CSS value
     * @return mixed - a CSS value, or a map of CSS values for typography tokens
     */
    public static function tokenValueToCss(mixed $value, ?string $type, callable $resolvePointer): mixed
    {
        $toCss = static fn (mixed $subValue, ?string $subType = null): mixed
            => self::tokenValueToCss($subValue, $subType, $resolvePointer);

        if (is_array($value) && is_string($value['$ref'] ?? null)) {
            return $resolvePointer($value['$ref']);
        }

        if (is_string($value)) {
            if ($type === 'fontWeight' && isset(self::FONT_WEIGHT_ALIASES[$value])) {
                return self::FONT_WEIGHT_ALIASES[$value];
            }

            $css = self::referencesToCssVars($value);

            return $type === 'fontFamily' ? self::quoteFontFamily($css) : $css;
        }

        $isMap = is_array($value);
        $isList = $value instanceof ValueList;

        switch ($type ?? self::inferType($value)) {
            case 'color':
                return $isMap ? Color::toCss($value, $toCss) : $value;

            case 'dimension':
            case 'duration':
                return $isMap
                    ? Utils::toString($toCss($value['value'] ?? null)) . Utils::toString($value['unit'] ?? '')
                    : $value;

            case 'cubicBezier':
                return $isList ? 'cubic-bezier(' . $value->map($toCss)->join(', ') . ')' : $value;

            case 'fontFamily':
                return $isList
                    ? $value->map(static fn ($family) => $toCss($family, 'fontFamily'))->join(', ')
                    : $value;

            case 'strokeStyle':
                // A dash array has no CSS equivalent, `dashed` is the closest fallback
                return $isMap ? 'dashed' : $value;

            case 'border':
                return $isMap
                    ? self::joinParts([
                        $toCss($value['width'] ?? null, 'dimension'),
                        $toCss($value['style'] ?? null, 'strokeStyle'),
                        $toCss($value['color'] ?? null, 'color'),
                    ])
                    : $value;

            case 'transition':
                return $isMap
                    ? self::joinParts([
                        $toCss($value['duration'] ?? null, 'duration'),
                        $toCss($value['timingFunction'] ?? null, 'cubicBezier'),
                        $toCss($value['delay'] ?? null, 'duration'),
                    ])
                    : $value;

            case 'shadow':
                $shadows = $isList ? $value->items : [$value];

                return implode(', ', array_map(static function ($shadow) use ($toCss): string {
                    if (!self::isComposite($shadow)) {
                        return Utils::toString(self::isScalar($shadow) ? $shadow : $toCss($shadow, 'shadow'));
                    }

                    return self::joinParts([
                        !empty($shadow['inset']) ? 'inset' : '',
                        $toCss($shadow['offsetX'] ?? null, 'dimension'),
                        $toCss($shadow['offsetY'] ?? null, 'dimension'),
                        $toCss($shadow['blur'] ?? null, 'dimension'),
                        $toCss($shadow['spread'] ?? null, 'dimension'),
                        $toCss($shadow['color'] ?? null, 'color'),
                    ]);
                }, $shadows));

            case 'gradient':
                if (!$isList) {
                    return $value;
                }

                return $value->map(static function ($stop) use ($toCss): string {
                    if (!self::isComposite($stop)) {
                        return Utils::toString(self::isScalar($stop) ? $stop : $toCss($stop, 'gradient'));
                    }

                    $position = $toCss($stop['position'] ?? null, 'number');
                    $percentage = is_int($position) || is_float($position)
                        ? Utils::toString(min(max($position, 0), 1) * 100) . '%'
                        : 'calc(' . Utils::toString($position) . ' * 100%)';

                    return Utils::toString($toCss($stop['color'] ?? null, 'color')) . ' ' . $percentage;
                })->join(', ');

            case 'typography':
                if (!$isMap) {
                    return $value;
                }

                $result = [];
                foreach ($value as $key => $part) {
                    $result[$key] = $toCss($part, self::TYPOGRAPHY_TYPES[$key] ?? null);
                }

                return $result;
        }

        return $isList ? $value->map($toCss) : $value;
    }

    /**
     * Splits a value on a separator, ignoring separators inside functions.
     *
     * @param string $value
     * @param string $separator - a single character, or `' '` for any whitespace
     * @return list<string>
     */
    public static function splitTopLevel(string $value, string $separator): array
    {
        $parts = [''];
        $depth = 0;

        foreach (str_split($value) as $character) {
            if ($character === '(') {
                $depth++;
            }
            if ($character === ')') {
                $depth--;
            }

            $isSeparator = $separator === ' ' ? ctype_space($character) : $character === $separator;

            if ($depth === 0 && $isSeparator) {
                $parts[] = '';
            } else {
                $parts[count($parts) - 1] .= $character;
            }
        }

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }

    /**
     * Converts a numeric string to an int or float, e.g. `'4'` to `4` and `'.5'` to `0.5`.
     *
     * @param string $value
     * @return int|float
     */
    public static function toNumber(string $value): int|float
    {
        return 0 + $value;
    }

    /**
     * @param string $value
     * @return array{value: int|float, unit: string}|null
     */
    private static function parseDimension(string $value): ?array
    {
        if ($value === '0') {
            return ['value' => 0, 'unit' => 'px'];
        }

        return preg_match('/^(' . self::NUMBER_PATTERN . ')(px|rem)$/i', $value, $match)
            ? ['value' => self::toNumber($match[1]), 'unit' => strtolower($match[2])]
            : null;
    }

    /**
     * @param string $value
     * @param callable(string): ?string $lookupReference
     * @return array<string, mixed>|string|null
     */
    private static function parseColorOrReference(string $value, callable $lookupReference): array|string|null
    {
        return preg_match(self::CSS_VAR_REGEX, $value, $variable)
            ? $lookupReference($variable[1])
            : Color::parse($value);
    }

    /**
     * @param string $value - e.g. `0 2px 1px -1px var(--shadow-color), inset 0 1px #000`
     * @param callable(string): ?string $lookupReference
     * @return array<string, mixed>|ValueList|null
     */
    private static function parseShadow(string $value, callable $lookupReference): array|ValueList|null
    {
        $shadows = [];

        foreach (self::splitTopLevel($value, ',') as $item) {
            $parts = self::splitTopLevel($item, ' ');
            $dimensions = [];
            $color = null;

            foreach ($parts as $part) {
                if ($part === 'inset') {
                    continue;
                }

                $dimension = self::parseDimension($part);

                if ($dimension) {
                    $dimensions[] = $dimension;
                } elseif ($color === null) {
                    $color = self::parseColorOrReference($part, $lookupReference);
                    if ($color === null) {
                        return null;
                    }
                } else {
                    return null;
                }
            }

            if (count($dimensions) < 2 || count($dimensions) > 4) {
                return null;
            }

            $zero = ['value' => 0, 'unit' => 'px'];
            $shadow = [
                'color' => $color ?? ['colorSpace' => 'srgb', 'components' => new ValueList([0, 0, 0]), 'hex' => '#000000'],
                'offsetX' => $dimensions[0],
                'offsetY' => $dimensions[1],
                'blur' => $dimensions[2] ?? $zero,
                'spread' => $dimensions[3] ?? $zero,
            ];

            if (in_array('inset', $parts, true)) {
                $shadow['inset'] = true;
            }

            $shadows[] = $shadow;
        }

        if (!$shadows) {
            return null;
        }

        return count($shadows) === 1 ? $shadows[0] : new ValueList($shadows);
    }

    /**
     * Converts a CSS value into a 2025.10 token.
     *
     * @param mixed $input - the CSS value
     * @param string $name - the CSS variable name (without `--`), used as type hint
     * @param callable(string): ?string $lookupReference - turns a CSS variable name into a `{token.path}` reference
     * @return array<string, mixed>|null - `null` when the value can't be expressed as a token
     */
    public static function cssToToken(mixed $input, string $name, callable $lookupReference): ?array
    {
        $isWeight = (bool) preg_match('/weight/i', $name);

        if (is_int($input) || is_float($input)) {
            $isFontWeight = $isWeight && $input >= 1 && $input <= 1000;

            return ['$type' => $isFontWeight ? 'fontWeight' : 'number', '$value' => $input];
        }

        if ($input instanceof ValueList) {
            $input = $input->join(', ');
        }

        if (!is_string($input)) {
            return null;
        }

        $value = trim($input);

        if (preg_match(self::CSS_VAR_REGEX, $value, $variable)) {
            $reference = $lookupReference($variable[1]);

            return $reference ? ['$value' => $reference] : null;
        }

        if (preg_match('/family/i', $name)) {
            $families = array_map(
                static fn (string $family): string => preg_replace('/^["\'](.*)["\']$/', '$1', $family),
                self::splitTopLevel($value, ','),
            );

            return ['$type' => 'fontFamily', '$value' => count($families) === 1 ? $families[0] : new ValueList($families)];
        }

        if ($isWeight && array_key_exists($value, self::FONT_WEIGHT_ALIASES)) {
            return ['$type' => 'fontWeight', '$value' => $value];
        }

        if (is_numeric($value)) {
            return self::cssToToken(self::toNumber($value), $name, $lookupReference);
        }

        if (preg_match('/^(' . self::NUMBER_PATTERN . ')\s*\/\s*(' . self::NUMBER_PATTERN . ')$/', $value, $ratio)) {
            return [
                '$type' => 'number',
                '$value' => (float) $ratio[1] / (float) $ratio[2],
                '$description' => $value,
            ];
        }

        $color = Color::parse($value);
        if ($color) {
            return ['$type' => 'color', '$value' => $color];
        }

        $dimension = self::parseDimension($value);
        if ($dimension) {
            return ['$type' => 'dimension', '$value' => $dimension];
        }

        if (preg_match('/^(' . self::NUMBER_PATTERN . ')(ms|s)$/i', $value, $duration)) {
            return [
                '$type' => 'duration',
                '$value' => ['value' => self::toNumber($duration[1]), 'unit' => strtolower($duration[2])],
            ];
        }

        $cubicBezierRegex = '/^cubic-bezier\(\s*(' . self::NUMBER_PATTERN . ')\s*,\s*(' . self::NUMBER_PATTERN
            . ')\s*,\s*(' . self::NUMBER_PATTERN . ')\s*,\s*(' . self::NUMBER_PATTERN . ')\s*\)$/i';
        if (preg_match($cubicBezierRegex, $value, $cubicBezier)) {
            return [
                '$type' => 'cubicBezier',
                '$value' => new ValueList(array_map([self::class, 'toNumber'], array_slice($cubicBezier, 1))),
            ];
        }

        $shadow = self::parseShadow($value, $lookupReference);

        return $shadow ? ['$type' => 'shadow', '$value' => $shadow] : null;
    }
}
