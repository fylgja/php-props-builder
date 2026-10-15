<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder;

use Fylgja\PropsBuilder\Formats\Tokens;

final class ToTokens
{
    public const DEFAULT_COLOR_KEYS = [
        'color',
        'white',
        'black',
        'gray',
        'red',
        'pink',
        'purple',
        'violet',
        'indigo',
        'blue',
        'cyan',
        'teal',
        'green',
        'lime',
        'yellow',
        'orange',
    ];

    /**
     * Replaces CSS variable references with token references, e.g. `var(--a-b)` with `{a.b.value}`.
     *
     * @param mixed $token
     * @param string $metaValueKey - e.g. `$value` or `value`
     * @return mixed
     */
    private static function tokenizeCssVar(mixed $token, string $metaValueKey): mixed
    {
        if ($token instanceof ValueList) {
            $token = $token->join(',');
        }

        if (!is_string($token)) {
            return $token;
        }

        return preg_replace_callback(
            Utils::CSS_VAR_REGEX,
            static fn (array $match): string => '{' . str_replace('-', '.', $match[1]) . ".{$metaValueKey}}",
            $token,
        );
    }

    /**
     * Creates a JSON token file from a map of CSS props.
     *
     * @param array<string|int, mixed> $props
     * @param (callable(string, list<string>): array<string, string>)|null $metaExtend - adds the meta information for a token key, defaults to `Tokens::meta()`
     * @param string $wrapper - nests the tokens under this key
     * @param bool $groupTypes - groups the tokens by their type
     * @param list<string> $colorKeys - keys treated as color tokens
     * @param bool $cssVarToToken - replace CSS variables with token references
     * @return string
     */
    public static function build(
        array $props,
        ?callable $metaExtend = null,
        string $wrapper = '',
        bool $groupTypes = true,
        array $colorKeys = self::DEFAULT_COLOR_KEYS,
        bool $cssVarToToken = false,
    ): string {
        $metaExtend ??= [Tokens::class, 'meta'];
        $isUsingDefaultFormat = $metaExtend === [Tokens::class, 'meta'];
        $metaValueKey = $isUsingDefaultFormat ? '$value' : 'value';
        $tokens = [];

        foreach (Utils::flatten($props) as $key => $token) {
            $meta = $metaExtend((string) $key, $colorKeys);
            $value = $cssVarToToken ? self::tokenizeCssVar($token, $metaValueKey) : $token;
            $metaType = $meta['type'] ?? $meta['$type'] ?? null;
            $entry = [$metaValueKey => $value, ...$meta];

            if ($groupTypes && $metaType) {
                $tokens[$metaType][$key] = $entry;
            } else {
                $tokens[$key] = $entry;
            }
        }

        if ($wrapper && Utils::isValidString($wrapper)) {
            $tokens = [$wrapper => $tokens];
        }

        return Json::encode($isUsingDefaultFormat ? $tokens : Utils::unflatten($tokens));
    }
}
