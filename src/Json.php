<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder;

final class Json
{
    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * Encodes a value the same way `JSON.stringify(value, null, 2)` does,
     * where maps are always objects and only a `ValueList` is an array.
     *
     * @param mixed $value
     * @param bool $pretty - indent with 2 spaces
     * @return string
     */
    public static function encode(mixed $value, bool $pretty = true): string
    {
        if (!$pretty) {
            return json_encode(self::prepare($value), self::FLAGS);
        }

        $json = json_encode(self::prepare($value), self::FLAGS | JSON_PRETTY_PRINT);

        return preg_replace_callback(
            '/^(?: {4})+/m',
            static fn (array $match): string => str_repeat('  ', intdiv(strlen($match[0]), 4)),
            $json,
        );
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function prepare(mixed $value): mixed
    {
        if ($value instanceof ValueList) {
            return array_map([self::class, 'prepare'], $value->items);
        }

        if (is_array($value)) {
            return (object) array_map([self::class, 'prepare'], $value);
        }

        return $value;
    }
}
