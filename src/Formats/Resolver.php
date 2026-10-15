<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder\Formats;

use Fylgja\PropsBuilder\ValueList;

/**
 * Reads a Design Tokens Resolver Module 2025.10 document.
 *
 * @source https://www.designtokens.org/tr/2025.10/resolver/
 */
final class Resolver
{
    /**
     * @param mixed $doc
     * @return bool
     */
    public static function isResolverDocument(mixed $doc): bool
    {
        return is_array($doc)
            && is_string($doc['version'] ?? null)
            && ($doc['resolutionOrder'] ?? null) instanceof ValueList;
    }

    /**
     * Splits a JSON Pointer (RFC 6901) into its unescaped keys.
     *
     * @param string $pointer - e.g. `#/sets/base`
     * @return list<string>
     * @throws \InvalidArgumentException when the pointer is not local to the document, e.g. a file reference
     */
    public static function parsePointer(string $pointer): array
    {
        if ($pointer !== '#' && !str_starts_with($pointer, '#/')) {
            throw new \InvalidArgumentException(
                "Cannot resolve \"\$ref\": \"{$pointer}\", only references within the same document are supported. Inline or merge the referenced file first.",
            );
        }

        $keys = array_filter(explode('/', substr($pointer, 2)), static fn (string $key): bool => $key !== '');

        return array_values(array_map(
            static fn (string $key): string => str_replace(['~1', '~0'], ['/', '~'], $key),
            $keys,
        ));
    }

    /**
     * @param mixed $doc
     * @param list<string|int> $path
     * @param string|null $pointer - only used for the error message
     * @return mixed
     * @throws \InvalidArgumentException when the path does not exist
     */
    public static function getPath(mixed $doc, array $path, ?string $pointer = null): mixed
    {
        $node = $doc;

        foreach ($path as $key) {
            $items = $node instanceof ValueList ? $node->items : $node;

            if (!is_array($items) || !array_key_exists($key, $items)) {
                $pointer ??= implode('/', $path);
                throw new \InvalidArgumentException("Cannot resolve \"\$ref\": \"{$pointer}\", the target does not exist");
            }

            $node = $items[$key];
        }

        return $node;
    }

    /**
     * Merges a list of sources (inline token trees or `$ref`s) on top of `$tokens`.
     *
     * @param array<string|int, mixed> $doc
     * @param array<string|int, mixed> $tokens
     * @param mixed $sources
     * @return array<string|int, mixed>
     */
    private static function mergeSources(array $doc, array $tokens, mixed $sources): array
    {
        foreach ($sources instanceof ValueList ? $sources->items : [] as $source) {
            $ref = is_array($source) ? $source['$ref'] ?? null : null;

            if (!is_string($ref)) {
                $tokens = Dtcg::mergeTokenTrees($tokens, $source);
                continue;
            }

            $target = self::getPath($doc, self::parsePointer($ref), $ref);
            $tokens = is_array($target) && ($target['sources'] ?? null) instanceof ValueList
                ? self::mergeSources($doc, $tokens, $target['sources'])
                : Dtcg::mergeTokenTrees($tokens, $target);
        }

        return $tokens;
    }

    /**
     * Flattens a resolver document into a single token tree.
     *
     * Every modifier resolves to its `default` context (or its first context).
     *
     * @param array<string|int, mixed> $doc
     * @return array<string|int, mixed>
     */
    public static function resolve(array $doc): array
    {
        $tokens = [];

        foreach ($doc['resolutionOrder']->items as $entry) {
            $ref = is_array($entry) ? $entry['$ref'] ?? null : null;
            $isReference = is_string($ref);
            $path = $isReference ? self::parsePointer($ref) : [];
            $node = $isReference ? self::getPath($doc, $path, $ref) : $entry;
            $type = $isReference
                ? ($path[0] === 'modifiers' ? 'modifier' : 'set')
                : ($entry['type'] ?? null);

            if (!is_array($node)) {
                continue;
            }

            if ($type === 'modifier') {
                $contexts = is_array($node['contexts'] ?? null) ? $node['contexts'] : [];
                $context = $node['default'] ?? array_key_first($contexts);

                $tokens = self::mergeSources($doc, $tokens, $contexts[$context] ?? null);
            } else {
                $tokens = self::mergeSources($doc, $tokens, $node['sources'] ?? null);
            }
        }

        return $tokens;
    }
}
