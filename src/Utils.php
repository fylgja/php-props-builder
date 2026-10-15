<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder;

final class Utils
{
    public const CSS_VAR_REGEX = '/var\(--([a-zA-Z0-9-]+)\)/';

    /**
     * Converts input props into the internal shape: maps are arrays, lists are a `ValueList`.
     * Objects (e.g. from `json_decode()` without `associative`) are always maps,
     * so a scale keyed `0`, `1`, `2` survives as a map.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function normalize(mixed $value): mixed
    {
        if ($value instanceof ValueList) {
            return $value->map([self::class, 'normalize']);
        }

        if (is_object($value)) {
            return array_map([self::class, 'normalize'], get_object_vars($value));
        }

        if (is_array($value)) {
            $items = array_map([self::class, 'normalize'], $value);

            return $value !== [] && array_is_list($value) ? new ValueList($items) : $items;
        }

        return $value;
    }

    /**
     * Casts a scalar to a string the way JavaScript does, e.g. `0.5` and not `0.50000000000000`.
     *
     * @param mixed $value
     * @return string
     */
    public static function toString(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_float($value) => str_replace('.0e', 'e', json_encode($value)),
            $value instanceof ValueList => $value->join(','),
            $value === null => '',
            default => (string) $value,
        };
    }

    /**
     * Converts a string to kebab case.
     *
     * @param string|int $string
     * @return string
     */
    public static function kebabCase(string|int $string): string
    {
        return strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', (string) $string));
    }

    /**
     * Checks if the value is a string without special characters.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isValidString(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9\s]+$/', $value) === 1;
    }

    /**
     * Gets the output type based on the filename.
     *
     * @param string $filename
     * @return string
     */
    public static function fileType(string $filename): string
    {
        return match (true) {
            str_ends_with($filename, 'figma-tokens.json'),
            str_ends_with($filename, 'figma-tokens.sync.json') => 'figma',
            str_ends_with($filename, 'style-dictionary-tokens.json') => 'style-dictionary',
            str_ends_with($filename, '.resolver.json') => 'dtcg',
            str_ends_with($filename, 'tokens.json') => 'tokens',
            str_ends_with($filename, '.cjs') => 'cjs',
            str_ends_with($filename, '.js'), str_ends_with($filename, '.mjs') => 'mjs',
            default => array_slice(preg_split('/[.;+_]/', $filename), -1)[0],
        };
    }

    /**
     * Flattens a nested map into a single level map.
     *
     * @param array<string|int, mixed> $obj
     * @param string $separator - joins the nested keys
     * @return array<string|int, mixed>
     */
    public static function flatten(array $obj, string $separator = '-'): array
    {
        $result = [];

        foreach ($obj as $key => $value) {
            if (is_array($value)) {
                foreach (self::flatten($value, $separator) as $nestedKey => $nestedValue) {
                    $result[$key . $separator . $nestedKey] = $nestedValue;
                }
            } elseif ($value !== null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Turns a flat map into a nested map, splitting the keys on `$separator`.
     *
     * @param array<string|int, mixed> $obj
     * @param string $separator
     * @return array<string|int, mixed>
     */
    public static function unflatten(array $obj, string $separator = '-'): array
    {
        $result = [];

        foreach ($obj as $path => $value) {
            $keys = explode($separator, (string) $path);
            $last = array_pop($keys);
            $current = &$result;

            foreach ($keys as $key) {
                if (!is_array($current[$key] ?? null)) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }

            $current[$last] = $value;
            unset($current);
        }

        return $result;
    }

    /**
     * Unwraps a nested map by following a path of keys, dropping everything above it.
     * Useful for token files that wrap the tokens in structural keys (e.g. `tokens.values`).
     *
     * @param mixed $obj
     * @param string|list<string> $keyPath - dot/dash separated path, or list of keys
     * @return mixed - the value at `$keyPath`, or `$obj` when the path does not exist
     */
    public static function stripPrefixPath(mixed $obj, string|array $keyPath): mixed
    {
        if ($keyPath === '' || $keyPath === []) {
            return $obj;
        }

        $keys = is_array($keyPath) ? $keyPath : preg_split('/[.-]/', $keyPath);
        $current = $obj;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $obj;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Recursively renames map keys, e.g. `['colors' => 'color']`.
     *
     * @param mixed $obj
     * @param array<string|int, string>|null $renameMap
     * @return mixed
     */
    public static function renameKeys(mixed $obj, ?array $renameMap): mixed
    {
        if (!$renameMap || !is_array($obj)) {
            return $obj;
        }

        $result = [];

        foreach ($obj as $key => $value) {
            $result[$renameMap[$key] ?? $key] = self::renameKeys($value, $renameMap);
        }

        return $result;
    }
}
