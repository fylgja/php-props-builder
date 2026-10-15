<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder;

use Fylgja\PropsBuilder\Formats\Dtcg;
use Fylgja\PropsBuilder\Formats\Resolver;
use Fylgja\PropsBuilder\Formats\Stitch;

final class FromTokens
{
    /**
     * Converts a design token tree from a specific syntax to a plain, nested map of CSS values.
     *
     * Token references (e.g. `{color.brand.primary}`) become CSS variables (e.g. `var(--color-brand-primary)`).
     *
     * - `default` supports the Design Tokens 2025.10 format, including Resolver documents
     * - `figma` also collapses a redundant nested `color`/`colors` group and unwraps a top-level `other` group
     * - `stitch` takes the raw Markdown content and reads its frontmatter
     *
     * @param mixed $props - the token tree, or (for `stitch`) the raw Markdown content
     * @param string $syntax - `default`, `figma`, `style-dictionary` or `stitch`
     * @return mixed
     */
    public static function convert(mixed $props, string $syntax = 'default'): mixed
    {
        if ($syntax === 'stitch') {
            $props = Utils::normalize(Stitch::parse(is_string($props) ? $props : ''));
        }

        if ($syntax !== 'figma') {
            return self::fromDtcg(Resolver::isResolverDocument($props) ? Resolver::resolve($props) : $props);
        }

        return self::fromFigma($props, true);
    }

    /**
     * @param mixed $props
     * @param bool $isTopLevel
     * @return mixed
     */
    private static function fromFigma(mixed $props, bool $isTopLevel): mixed
    {
        if (is_array($props) && array_key_exists('value', $props)) {
            $value = $props['value'];

            return is_string($value) ? Dtcg::referencesToCssVars($value) : $value;
        }

        if (!is_array($props)) {
            return $props;
        }

        $result = [];

        foreach ($props as $key => $value) {
            // Metadata keys (e.g. `$description`, `$type`) are not tokens
            if (str_starts_with((string) $key, '$')) {
                continue;
            }

            $result[$key] = self::collapseRedundantColorGroup((string) $key, self::fromFigma($value, false));
        }

        return $isTopLevel ? self::unwrapTopLevelOtherGroup($result) : $result;
    }

    /**
     * Some Figma exports wrap the color group in a redundant nested
     * `color`/`colors` key, producing names like `--colors-color-primary`.
     *
     * Other groups (e.g. `sizing`, `borderWidth`) are intentionally not unwrapped,
     * use the `stripPrefix`/`rename` options for those.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    private static function collapseRedundantColorGroup(string $key, mixed $value): mixed
    {
        if (($key !== 'color' && $key !== 'colors') || !is_array($value)) {
            return $value;
        }

        foreach (['color', 'colors'] as $nestedKey) {
            if (array_key_exists($nestedKey, $value)) {
                return $value[$nestedKey];
            }
        }

        return $value;
    }

    /**
     * Figma wraps ungrouped tokens in a top-level `other` key, which is safe to unwrap.
     * A nested `other` key is a regular token group and is left alone.
     *
     * @param array<string|int, mixed> $props
     * @return array<string|int, mixed>
     */
    private static function unwrapTopLevelOtherGroup(array $props): array
    {
        $other = $props['other'] ?? null;

        if (!is_array($other)) {
            return $props;
        }

        unset($props['other']);

        return array_replace($props, $other);
    }

    /**
     * Converts a Design Tokens (2025.10) tree into a plain, nested map with CSS values.
     *
     * - `$root` tokens are written as `DEFAULT`, which is dropped from the CSS variable name
     * - `$extends` groups are deep merged with the group they extend
     * - `$type` is inherited from parent groups
     * - Curly brace references and `$ref` JSON Pointers to a token become CSS variables,
     *   pointers into a value (e.g. `#/color/blue/$value/components/0`) are resolved to that value
     *
     * @param mixed $root - the whole token tree, used to resolve references
     * @return mixed
     */
    private static function fromDtcg(mixed $root): mixed
    {
        $resolvePointer = static function (string $pointer) use ($root, &$resolvePointer): mixed {
            $path = Resolver::parsePointer($pointer);
            $valueIndex = array_search('$value', $path, true);

            if ($valueIndex === false || $valueIndex === count($path) - 1) {
                return Dtcg::pathToCssVar($path);
            }

            return Dtcg::tokenValueToCss(Resolver::getPath($root, $path, $pointer), null, $resolvePointer);
        };

        $applyExtends = static function (array $group, array $trail = []) use ($root, &$applyExtends): array {
            $extends = $group['$extends'] ?? null;
            if (!is_string($extends)) {
                return $group;
            }

            unset($group['$extends']);

            $path = str_starts_with($extends, '{')
                ? explode('.', substr($extends, 1, -1))
                : Resolver::parsePointer($extends);
            $key = implode('/', $path);

            if (in_array($key, $trail, true)) {
                throw new \InvalidArgumentException("Circular \"\$extends\" reference: \"{$extends}\"");
            }

            $target = Resolver::getPath($root, $path, $extends);
            $target = $applyExtends(is_array($target) ? $target : [], [...$trail, $key]);

            return Dtcg::mergeTokenTrees($target, $group);
        };

        $convert = static function (mixed $node, ?string $inheritedType) use (&$convert, $applyExtends, $resolvePointer): mixed {
            if (Dtcg::isToken($node)) {
                $type = $node['$type'] ?? $inheritedType;

                return Dtcg::tokenValueToCss($node['$value'], is_string($type) ? $type : null, $resolvePointer);
            }

            if (!is_array($node)) {
                return $node;
            }

            $group = $applyExtends($node);
            $type = $group['$type'] ?? $inheritedType;
            $result = [];

            foreach ($group as $key => $value) {
                // Metadata keys (e.g. `$description`, `$type`) are not tokens
                if ($key !== '$root' && str_starts_with((string) $key, '$')) {
                    continue;
                }

                $result[$key === '$root' ? 'DEFAULT' : $key] = $convert($value, is_string($type) ? $type : null);
            }

            return $result;
        };

        return $convert($root, null);
    }
}
