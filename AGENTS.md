# Agent guide: keeping the PHP port in sync

This package is a PHP port of the Node package `@fylgja/props-builder`.
The Node version is the source of truth. Every change there should land here with the same behavior and byte identical output.
This guide is for anyone (human or AI) doing that sync.

## Upstream

| | |
| --- | --- |
| Repository | [fylgja/fylgja](https://github.com/fylgja/fylgja), directory `props-builder/` |
| Branch | `main` |
| Local checkout (default) | `../fylgja/props-builder`, relative to this repo |

### Sync state

Update this table at the end of every sync.

| | |
| --- | --- |
| Last synced upstream commit | `b391b2c` (ADD Support for the Design Tokens 2025.10 spec, merged in #413) |
| Upstream npm version | `2.2.0`, plus the Unreleased changes in its CHANGELOG (Design Tokens 2025.10) |

Releases of this package use the same version number as the npm release they match.

## Sync workflow

1. **Find what changed upstream.** From the upstream checkout:

   ```sh
   git fetch origin
   git log --oneline <last synced commit>..origin/main -- props-builder
   git diff <last synced commit> origin/main -- props-builder
   ```

   Also read `props-builder/CHANGELOG.md` for the intent behind the changes.

2. **Port the source.** Use the file map below. Keep the structure, names and order of functions close to the JavaScript, so the next diff is easy to follow.
3. **Port the tests.** Every new or changed upstream test gets a PHPUnit equivalent (see the file map).
4. **Add a parity case** to `tests/parity/cases.json` for any new option, format or value type. Input files live next to it.
5. **Run both checks.** Both must pass before committing:

   ```sh
   composer test
   composer parity            # or: tests/parity/run.sh /path/to/fylgja/props-builder
   ```

   The `parity` job in CI runs the same check against upstream `main`, so a failing job there means upstream has changes that are not ported yet.

6. **Update the docs.** Mirror README changes from upstream into `README.md`, translated to PHP syntax (named arguments instead of an options object).
7. **Update the sync state** table above.

Commit with the house style: a prefix (`ADD`, `IMP`, `DEL`, `FIX`, `UPD`), imperative mood, around 50 characters, e.g. `IMP Sync with props-builder 2.3.0`.

## File map

| Node (`props-builder/`) | PHP (`src/`) |
| --- | --- |
| `index.js` (`propsBuilder`) | `PropsBuilder.php` (`PropsBuilder::build`) |
| `src/utils.js` | `Utils.php` |
| `src/from-tokens.js` | `FromTokens.php` |
| `src/to-tokens.js` | `ToTokens.php` |
| `src/to-style.js` | `ToStyle.php` |
| `src/to-css-jit.js` | `ToCssJit.php` |
| `src/to-dtcg.js` | `ToDtcg.php` |
| `src/formats/color.js` | `Formats/Color.php` |
| `src/formats/dtcg.js` | `Formats/Dtcg.php` |
| `src/formats/resolver.js` | `Formats/Resolver.php` |
| `src/formats/stitch.js` | `Formats/Stitch.php` |
| `src/formats/tokens.js` | `Formats/Tokens.php` |
| `src/formats/figma.js` | `Formats/Figma.php` |
| `src/formats/style-dictionary.js` | `Formats/StyleDictionary.php` |
| `JSON.stringify(value, null, 2)` | `Json.php` |
| JavaScript arrays | `ValueList.php` |

| Node tests (`props-builder/tests/`) | PHP tests (`tests/`) |
| --- | --- |
| `props-builder.test.js` | `PropsBuilderTest.php` |
| `dtcg.test.js` | `DtcgTest.php` |
| `stitch.test.js` | `StitchTest.php` |
| `fixtures.js` | `Fixtures.php` |
| `test.tokens.json`, `test.stitch.md` | `fixtures/` (copy them over when they change) |

## Porting rules

These are the places where a literal translation gives the wrong result.

### Objects, arrays and `ValueList`

PHP arrays are both maps and lists, JavaScript has objects and arrays. Internally:

- every PHP `array` is a map (a JavaScript object),
- every list is a `ValueList` (a JavaScript array).

`Utils::normalize()` converts the input once, in `PropsBuilder::build()`. A PHP list becomes a `ValueList`, an object (`stdClass`) always becomes a map, so a scale keyed `0`, `1`, `2` from `json_decode()` stays a map.

Translate the checks like this:

| JavaScript | PHP |
| --- | --- |
| `isPlainObject(value)` | `is_array($value)` |
| `Array.isArray(value)` | `$value instanceof ValueList` |
| `value.map(fn)` on an array | `$value->map($fn)` |
| `value.join(", ")` on an array | `$value->join(', ')` |
| `[a, b]` as a token value | `new ValueList([$a, $b])` |
| `Object.hasOwn(obj, key)` | `array_key_exists($key, $obj)` |
| `obj.key` being `undefined` | `$obj['key'] ?? null` |

Never return a plain PHP list where JavaScript returns an array, it would be encoded as an object or iterated as a map.

### Numbers and strings

- Cast values to strings with `Utils::toString()`, never with `(string)` or interpolation of a raw value. It writes floats the way JavaScript does (`0.1`, not `0.10000000000000001`) and joins a `ValueList` with `,` like `Array.prototype.toString()`.
- JavaScript has one number type. Where a JavaScript calculation gives an integral number that ends up in JSON, return an `int` (see `Color::round()`), so the output is `1` and not `1.0`.
- `Number(value)` on a string is `Dtcg::toNumber()`, `isNaN(Number(value))` is `!is_numeric()`.
- Array keys like `"1"` become integers in PHP. Cast keys with `(string)` before string functions (`str_starts_with`, `kebabCase`), `strict_types` is on.

### JSON output

Always use `Json::encode()`. It turns maps into objects (`{}` even when empty or keyed `0..n`), a `ValueList` into an array, and indents with 2 spaces like `JSON.stringify(value, null, 2)`.

### Side effects

- `console.warn` becomes the `onWarning` callable, which defaults to `error_log()`. A library must not echo, since it runs inside web requests.
- `console.log` hints are dropped.
- Errors thrown in JavaScript become `\InvalidArgumentException` with the same message, tests match on the message.

### Regular expressions

Copy the pattern as is and wrap it in `/.../`. Escape `/` inside the pattern, and add the flags (`i`) after the closing delimiter. A global regex used with `matchAll` or `replace` becomes `preg_match_all` or `preg_replace_callback`.

## Intentional differences

Do not "fix" these to match the Node version:

- **Key order.** JavaScript sorts integer keys of an object first. PHP keeps insertion order. Parity input files put integer keys first so both orders match.
- **Return value.** `build()` always returns the output, also when it writes the file.
- **Bug guards.** A number inside a `shadow` or `gradient` list would recurse forever in JavaScript, PHP casts it to a string (`Dtcg::isScalar()`). Empty parts are filtered from shorthands, so a missing part gives no double space.

When a new upstream change exposes another unavoidable difference, list it here and in the README.

## Code style

- PHP 8.1 is the minimum, so no `readonly` classes or other 8.2+ features.
- `declare(strict_types=1);`, PSR-12 with 4 space indents, the Fylgja license header on top of every file.
- A docblock on every function: a short description, `@param` and `@return`. Comment why, not what.
- Classes are `final` with static methods, the same shape as the plain functions upstream.
- US English in code and docs.
