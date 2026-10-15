# Fylgja Props Builder for PHP

[![Fylgja](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Ffylgja%2F.github%2Frefs%2Fheads%2Fmain%2Fassets%2Fbadge%2Fv1.0.0.json)](https://fylgja.dev/)
[![CI](https://img.shields.io/github/actions/workflow/status/fylgja/php-props-builder/test.yml?color=%230969da&label=&branch=main&logo=github&labelColor=%232b1c2c)](https://github.com/fylgja/php-props-builder/actions)
[![Version](https://img.shields.io/packagist/v/fylgja/props-builder?color=%230969da&label=&logo=packagist&logoColor=white&labelColor=%232b1c2c)](https://packagist.org/packages/fylgja/props-builder)
[![PHP](https://img.shields.io/packagist/dependency-v/fylgja/props-builder/php?color=%230969da&label=&logo=php&logoColor=white&labelColor=%232b1c2c)](https://packagist.org/packages/fylgja/props-builder)
[![License](https://img.shields.io/github/license/fylgja/php-props-builder?color=%23ffb900&labelColor=%232b1c2c)](/LICENSE)

A PHP port of [@fylgja/props-builder](https://www.npmjs.com/package/@fylgja/props-builder).
It converts PHP arrays or JSON files into CSS custom properties, SCSS variables or Design Token files,
so the same tokens can be built from backend logic without Node.

The output matches the JavaScript version, so both can be used side by side.

## Installation

```sh
composer require fylgja/props-builder
```

Requires PHP 8.1 or newer.

## Usage

```php
use Fylgja\PropsBuilder\PropsBuilder;

PropsBuilder::build(
    [
        'color' => [
            'red' => '#f00',
            'green' => '#0f0',
            'blue' => '#00f',
        ],
    ],
    'output.css',
);
```

This writes `output.css` with the following content:

```css
:where(:root) {
  --color-red: #f00;
  --color-green: #0f0;
  --color-blue: #00f;
}
```

`build()` always returns the generated output, so it can also be used without writing a file.

### Direct output

Set `writeToFile: false` to only get the output as a string, for example to render the tokens in a `<style>` tag.
The filename is still required, since it picks the output format, but nothing is written to it.

```php
<?php

use Fylgja\PropsBuilder\PropsBuilder;

$css = PropsBuilder::build(
    [
        'color' => [
            'primary' => '#1d4ed8',
            'surface' => '#ffffff',
        ],
        'radius' => '0.5rem',
    ],
    'tokens.css',
    writeToFile: false,
);
?>
<style><?= $css ?></style>
```

This renders:

```html
<style>:where(:root) {
	--color-primary: #1d4ed8;
	--color-surface: #ffffff;
	--radius: 0.5rem;
}
</style>
```

Instead of a filename with the right extension, you can also set the format with `parseAs`, e.g. `PropsBuilder::build($props, 'inline', parseAs: 'css', writeToFile: false)`.

To scope the tokens to a part of the page, such as a theme picked in the backend, set the `selector`:

```php
$css = PropsBuilder::build(
    $brandTokens,
    'tokens.css',
    writeToFile: false,
    selector: '[data-theme="brand"]',
);
```

Every output format works this way.
For example, `parseAs: 'json'` or `parseAs: 'css-jit'` gives the tokens as JSON, to pass them to JavaScript in a `<script type="application/json">` tag.

> [!IMPORTANT]
> The output is not escaped for HTML.
> If the token values come from user input, such as colors set in an admin panel, a value containing `</style>` can break out of the tag.
> Validate those values, or escape the `<` character before printing the output:
> `str_replace('<', '\3C ', $css)` for CSS, and `str_replace('<', '\u003c', $json)` for JSON.

### Use case: a theme set in the backend

A common case is letting store owners or editors pick the brand colors in an admin panel.
The default tokens stay in your stylesheet, and only the brand values are built per request and printed in the page head.

```php
<?php

use Fylgja\PropsBuilder\PropsBuilder;

$settings = loadThemeSettings(); // e.g. from the database or a config file

$theme = [
    'color' => [
        'primary' => $settings['primary'],
        'surface' => $settings['surface'],
    ],
    'color-@media:dark' => [
        'primary' => $settings['primary_dark'],
        'surface' => $settings['surface_dark'],
    ],
];

$css = PropsBuilder::build($theme, 'theme.css', writeToFile: false);
?>
<head>
    <link rel="stylesheet" href="/css/app.css">
    <style><?= str_replace('<', '\3C ', $css) ?></style>
</head>
```

The `-@media:dark` group becomes a dark mode block with the same custom property names:

```css
:where(:root) {
	--color-primary: #1d4ed8;
	--color-surface: #ffffff;
}

@media (prefers-color-scheme: dark) {
	:where(:root) {
		--color-primary: #93c5fd;
		--color-surface: #0f172a;
	}
}
```

Print the `<style>` tag after the stylesheet. Both use the same selector, so the one that comes last wins.

When the settings rarely change, you can also build a file once when they are saved, instead of on every request.
The browser can then cache it like any other stylesheet:

```php
PropsBuilder::build($theme, __DIR__ . '/public/css/theme.css');
```

### Options

The options are named arguments, the same as the options object in the JavaScript version.

```php
PropsBuilder::build(
    $props, // Required: array, object or (for Stitch input) a Markdown string.
    $filename, // Required: name of the output file, also picks the output format.
    parseAs: 'auto', // Output format, defaults to the format of the filename.
    writeToFile: true, // Write the output to $filename.
    selector: ':where(:root)', // CSS selector for the custom properties (css output only).
    wrapper: '', // Nests the Design Tokens under this key (e.g. for Figma).
    name: 'Design Tokens', // Name of the Design Tokens Resolver (dtcg output only).
    description: '', // Description of the Design Tokens Resolver (dtcg output only).
    banner: '', // Prepended to the output as is.
    inputTypeTokens: false, // Set to true if $props is a Design Tokens file.
    inputTypeSyntax: 'default', // Syntax of the input tokens: default, figma, style-dictionary or stitch.
    stripPrefix: '', // Dot/dash separated key path (or list of keys) to unwrap (e.g. 'tokens.values').
    rename: null, // Map of [currentKey => newKey], applied recursively (e.g. ['colors' => 'color']).
    onWarning: null, // Receives warnings, such as skipped dtcg tokens. Defaults to error_log().
);
```

Options can also be passed from an array with `PropsBuilder::build($props, $filename, ...$options)`.

### Lists and scales

PHP arrays are both lists and maps, so a list is treated as a value list, the same as a JSON array.
A value list is joined with a comma, e.g. the layers of a shadow or a font stack.

```php
'shadow' => [
    1 => ['0 1px 2px #0002', '0 2px 4px #0002'], // --shadow-1: 0 1px 2px #0002, 0 2px 4px #0002;
],
```

A scale keyed from `0` is also a list in PHP. Pass it as an object to keep it a scale:

```php
'gray' => (object) ['#f8f9fa', '#f1f3f5', '#e9ecef'], // --gray-0, --gray-1, --gray-2
```

For JSON files, decode them as objects (the default of `json_decode()`), so every JSON object stays a map:

```php
$tokens = json_decode(file_get_contents('tokens.json'));
```

### Output formats

The output format is picked from the filename, unless `parseAs` is set.

| Filename                           | `parseAs`            | Output                                      |
| ---------------------------------- | -------------------- | ------------------------------------------- |
| `*.css`                            | `css`                | CSS custom properties                       |
| `*.scss`                           | `scss`               | SCSS variables                              |
| `*.js`, `*.mjs`, `*.cjs`, `*.json` | `mjs`, `cjs`, `json` | The props as is                             |
| `*.resolver.json`                  | `dtcg`               | Design Tokens Resolver (2025.10)            |
| `*tokens.json`                     | `tokens`             | Design Tokens (legacy draft syntax)         |
| `*figma-tokens.json`               | `figma`              | Figma Tokens                                |
| `*style-dictionary-tokens.json`    | `style-dictionary`   | Style Dictionary tokens                     |
|                                    | `css-jit`            | A flat object for JIT tools, like jit-props |

### To Design Tokens 2025.10

The `dtcg` output follows the [Design Tokens 2025.10 specification](https://www.designtokens.org/tr/2025.10/).
Values are converted to typed token values, e.g. `0.5rem` becomes a `dimension` token with `{ "value": 0.5, "unit": "rem" }`,
and `var(--shadow-color)` becomes a `{shadow-color.$root}` reference.

```php
PropsBuilder::build($props, 'tokens/fylgja.resolver.json', name: 'Fylgja');
```

All tokens go in a single set of a Resolver document. Dark mode values (`@media:dark`) are left out.

The specification has no way to express some CSS values, such as `calc()`, `clamp()`, `var()` inside a color, or units other than `px` and `rem` (or `ms` and `s` for durations).
These tokens are skipped with a warning, together with any token referencing them.
The warning goes to `error_log()`, pass `onWarning` to handle it yourself.

### From Design Tokens to CSS

```php
use Fylgja\PropsBuilder\PropsBuilder;

$tokens = json_decode(file_get_contents('path/to/your/tokens.json'));

PropsBuilder::build($tokens, 'tokens.css', inputTypeTokens: true);
```

Configure the input based on the format of your file:

- **A simple key value file:** no options needed.
- **A W3C Design Tokens file (including 2025.10 and Resolver files):** set `inputTypeTokens: true`.
  Design Tokens 2025.10 values, such as color objects or dimensions, are converted to CSS values.
  Composite tokens become CSS shorthands (`border`, `shadow`, `transition`, and gradient color stops),
  except for `typography`, which becomes a custom property per part (e.g. `--heading-font-size`).
  Groups support `$type` inheritance, `$root` tokens, and `$extends`.
  Both `{token.path}` references and `$ref` JSON Pointers within the same file are supported.
  For a Resolver file, all sets are merged in the resolution order, and each modifier uses its `default` context.
  A `$ref` to another file is not supported, merge those files into a single document first.
- **A Figma Tokens file:** set `inputTypeTokens: true` and `inputTypeSyntax: 'figma'`.
- **A Google Stitch Markdown export:** set `inputTypeTokens: true` and `inputTypeSyntax: 'stitch'`,
  and pass the raw Markdown content (a string) as `$props`.
  Only the YAML frontmatter is used, and every key in it is converted to tokens, except for known metadata keys (`name`, `description`).
  The `colors` group is renamed to the singular `color` (unless a `color` group already exists).

## Differences from the JavaScript version

- JavaScript sorts integer keys of an object before the other keys, PHP keeps the order they were written in.
  The output is the same, only the order of some properties can differ.
- The `style-dictionary` input syntax does not log a hint to use the Style Dictionary converter.

## Development

```sh
composer install
composer test
composer parity
```

`composer parity` runs the cases in `tests/parity` through both this package and the Node version, and fails on any difference in the output.
It expects a checkout of [fylgja/fylgja](https://github.com/fylgja/fylgja) next to this repo, or a path to its `props-builder` directory in `PROPS_BUILDER_JS`.

See [AGENTS.md](AGENTS.md) for how to keep this package in sync with the Node version.
