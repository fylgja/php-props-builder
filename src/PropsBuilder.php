<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder;

use Fylgja\PropsBuilder\Formats\Figma;
use Fylgja\PropsBuilder\Formats\StyleDictionary;

final class PropsBuilder
{
    /**
     * Builds tokens in any syntax from a PHP array or a decoded JSON object,
     * either into a style syntax such as CSS or into a Design Tokens syntax.
     *
     * PHP lists are value lists (e.g. shadow layers), the same as JSON arrays.
     * Pass a scale keyed `0`, `1`, `2` as an object, e.g. from `json_decode()` without `associative`.
     *
     * @param array<string|int, mixed>|object|string $props - the props, or (for `stitch` input) the raw Markdown content
     * @param string $filename - output file, also picks the output format when `$parseAs` is `auto`
     * @param string $parseAs - `auto`, `tokens`, `dtcg`, `figma`, `style-dictionary`, `scss`, `css`, `css-jit`, `cjs`, `mjs` or `json`
     * @param bool $writeToFile - write the output to `$filename`
     * @param string $selector - CSS selector for the custom properties (`css` only)
     * @param string $wrapper - nests the Design Tokens under this key
     * @param string $name - name of the Design Tokens Resolver (`dtcg` only)
     * @param string $description - description of the Design Tokens Resolver (`dtcg` only)
     * @param string $banner - prepended to the output as is
     * @param bool $inputTypeTokens - `$props` is a Design Tokens file
     * @param string $inputTypeSyntax - `default`, `figma`, `style-dictionary` or `stitch`
     * @param string|list<string> $stripPrefix - unwraps `$props` by following this key path (e.g. `tokens.values`)
     * @param array<string, string>|null $rename - map of `[currentKey => newKey]`, applied recursively (e.g. `['colors' => 'color']`)
     * @param (callable(string): void)|null $onWarning - receives warnings, such as skipped tokens, defaults to `error_log()`
     * @return string - the generated output
     * @throws \RuntimeException when the file can't be written
     */
    public static function build(
        array|object|string $props,
        string $filename,
        string $parseAs = 'auto',
        bool $writeToFile = true,
        string $selector = ':where(:root)',
        string $wrapper = '',
        string $name = 'Design Tokens',
        string $description = '',
        string $banner = '',
        bool $inputTypeTokens = false,
        string $inputTypeSyntax = 'default',
        string|array $stripPrefix = '',
        ?array $rename = null,
        ?callable $onWarning = null,
    ): string {
        $fileType = Utils::fileType($filename);
        $parseSyntax = $parseAs !== 'auto' ? $parseAs : $fileType;
        $jsPrefix = match ($fileType) {
            'mjs' => 'export default ',
            'cjs' => 'module.exports = ',
            default => '',
        };

        if (!is_string($props)) {
            $props = Utils::normalize($props);
        }

        if ($inputTypeTokens) {
            $props = FromTokens::convert($props, $inputTypeSyntax);
        }

        $props = Utils::stripPrefixPath($props, $stripPrefix);
        $props = Utils::renameKeys($props, $rename);
        $props = match (true) {
            $props instanceof ValueList => $props->items,
            is_array($props) => $props,
            default => [],
        };

        $data = match ($parseSyntax) {
            'tokens' => ToTokens::build($props, wrapper: $wrapper, groupTypes: false),
            'dtcg' => ToDtcg::build($props, $name, $description, $onWarning),
            'figma' => ToTokens::build($props, [Figma::class, 'meta'], $wrapper, cssVarToToken: true),
            'style-dictionary' => ToTokens::build($props, [StyleDictionary::class, 'meta'], $wrapper, cssVarToToken: true),
            'scss' => ToStyle::build($props, varSyntax: '$') . "\n",
            'css' => ToStyle::build($props, $selector) . "\n",
            'css-jit' => $jsPrefix . ToCssJit::build($props) . "\n",
            'cjs', 'mjs', 'json' => $jsPrefix . Json::encode($props) . "\n",
            default => '',
        };

        if ($banner !== '') {
            $data = "{$banner}\n{$data}";
        }

        if ($writeToFile && file_put_contents($filename, $data) === false) {
            throw new \RuntimeException("Could not write the props to \"{$filename}\"");
        }

        return $data;
    }
}
