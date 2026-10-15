<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder;

final class ToStyle
{
    /**
     * @param mixed $token
     * @return mixed
     */
    private static function toScssVar(mixed $token): mixed
    {
        if (!is_string($token)) {
            return $token;
        }

        return preg_replace_callback(
            Utils::CSS_VAR_REGEX,
            static fn (array $match): string => "#{\${$match[1]}}",
            $token,
        );
    }

    /**
     * Removes the first occurrence of `$search` from `$subject`.
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string
     */
    private static function replaceFirst(string $search, string $replace, string $subject): string
    {
        $position = strpos($subject, $search);

        return $position === false ? $subject : substr_replace($subject, $replace, $position, strlen($search));
    }

    /**
     * Creates CSS custom properties or SCSS variables from a map of CSS props.
     *
     * @param array<string|int, mixed> $props
     * @param string $selector - the CSS selector, `@theme` writes a Tailwind v4 theme block
     * @param string $mediaDark - media query for dark mode
     * @param string $varSyntax - `--` for CSS variables or `$` for SCSS
     * @return string
     */
    public static function build(
        array $props,
        string $selector = ':root',
        string $mediaDark = '@media (prefers-color-scheme: dark)',
        string $varSyntax = '--',
    ): string {
        $indent = $varSyntax === '--' ? "\t" : '';
        $isScss = $varSyntax === '$';
        $isTw4 = $selector === '@theme';

        $styles = '';
        $stylesDark = '';
        $appendedMeta = '';

        foreach (Utils::flatten($props) as $name => $value) {
            $name = (string) $name;
            $isProperty = str_ends_with($name, '-@');
            $isDarkMode = str_contains($name, '-@media:dark');

            if ($value instanceof ValueList) {
                $value = $value->join(', ');
            }

            if ($isProperty) {
                $appendedMeta .= ($appendedMeta ? "\n\n" : '') . ($isTw4 ? $indent : '') . Utils::toString($value);
                continue;
            }

            $name = Utils::kebabCase(str_replace('.', '', $name));

            if (str_ends_with($name, '-default')) {
                $name = self::replaceFirst('-default', '', $name);
            }

            $varName = $varSyntax . $name;

            if ($isDarkMode) {
                $varName = self::replaceFirst('-@media:dark', $isScss ? '-dark' : '', $varName);
            }

            // Wrap values with any of `/`, `>`, `<`, `=`, or wrapped in parentheses, in quotes
            if (is_string($value) && $isScss && preg_match('/[\/><=]|^\(.*\)$/', $value)) {
                $value = "\"{$value}\"";
            }

            if ($isScss) {
                $value = self::toScssVar($value);
            }

            $value = Utils::toString($value);

            if ($isDarkMode) {
                $stylesDark .= "{$indent}{$indent}{$varName}: {$value};\n";
            } else {
                $styles .= "{$indent}{$varName}: {$value};\n";
            }
        }

        if ($isScss) {
            $result = $styles . $stylesDark . ($appendedMeta ? "\n{$appendedMeta}" : '');
        } elseif ($isTw4) {
            $result = '';

            if ($styles || $appendedMeta) {
                $result = "{$selector} {\n{$styles}" . ($appendedMeta ? "\n{$appendedMeta}" : '') . "\n}\n";
            }

            $result .= $stylesDark ? "\n{$mediaDark} {\n\t:root {\n{$stylesDark}\t}\n}" : '';
        } else {
            $result = $styles ? "{$selector} {\n{$styles}}\n" : '';
            $result .= $stylesDark ? "\n{$mediaDark} {\n\t{$selector} {\n{$stylesDark}\t}\n}" : '';
            $result .= $appendedMeta ? "\n{$appendedMeta}" : '';
        }

        return trim($result);
    }
}
