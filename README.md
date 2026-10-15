# Fylgja Props Builder for PHP

[![License](https://img.shields.io/github/license/fylgja/fylgja?color=%23234)](https://github.com/fylgja/fylgja/blob/main/LICENSE)

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

`build()` always returns the generated output.
Set `writeToFile: false` to only get the string, e.g. to inline it in a page.

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
```
