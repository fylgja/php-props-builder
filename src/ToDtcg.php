<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder;

use Fylgja\PropsBuilder\Formats\Dtcg;

final class ToDtcg
{
    /**
     * Builds the CSS variable name the same way `ToStyle` does.
     *
     * @param list<string|int> $path
     * @return string
     */
    private static function pathToCssName(array $path): string
    {
        return Utils::kebabCase(implode('-', array_filter($path, static fn ($segment): bool => $segment !== '$root')));
    }

    /**
     * @param array<string|int, mixed> $props
     * @param list<string|int> $path
     * @param list<array{path: list<string|int>, name: string, value: mixed}> $leaves
     * @return list<array{path: list<string|int>, name: string, value: mixed}>
     */
    private static function collectLeaves(array $props, array $path = [], array $leaves = []): array
    {
        foreach ($props as $key => $value) {
            $key = (string) $key;

            // `-@` props are raw CSS (e.g. `@keyframes`) and `@media` props are not part of the token set
            if (str_ends_with($key, '-@') || str_contains($key, '@media')) {
                continue;
            }

            $leafPath = [...$path, $key === 'DEFAULT' ? '$root' : str_replace('.', '', $key)];

            if (is_array($value)) {
                $leaves = self::collectLeaves($value, $leafPath, $leaves);
            } else {
                $leaves[] = ['path' => $leafPath, 'name' => self::pathToCssName($leafPath), 'value' => $value];
            }
        }

        return $leaves;
    }

    /**
     * @param array<string|int, mixed> $tree
     * @param list<string|int> $path
     * @param array<string, mixed> $token
     * @return bool - `false` when the path collides with an existing token or group
     */
    private static function setToken(array &$tree, array $path, array $token): bool
    {
        $node = &$tree;
        $last = array_pop($path);

        foreach ($path as $key) {
            $node[$key] ??= [];
            if (!is_array($node[$key]) || Dtcg::isToken($node[$key])) {
                return false;
            }
            $node = &$node[$key];
        }

        if (array_key_exists($last, $node)) {
            return false;
        }

        $node[$last] = $token;

        return true;
    }

    /**
     * Converts a map of CSS props into a Design Tokens 2025.10 token tree.
     *
     * Values the format can't express (e.g. `calc()`, `clamp()` or `em` units) are skipped,
     * and so are references to skipped tokens.
     *
     * @param array<string|int, mixed> $props
     * @return array{tokens: array<string|int, mixed>, skipped: list<string>}
     */
    public static function tokens(array $props): array
    {
        $leaves = self::collectLeaves($props);
        $paths = array_column($leaves, 'path', 'name');
        $lookupReference = static fn (string $name): ?string
            => isset($paths[$name]) ? '{' . implode('.', $paths[$name]) . '}' : null;
        $skipped = [];
        $entries = [];

        foreach ($leaves as $leaf) {
            $token = Dtcg::cssToToken($leaf['value'], $leaf['name'], $lookupReference);

            if ($token) {
                $entries[] = [...$leaf, 'token' => $token];
            } else {
                $skipped[] = $leaf['name'];
            }
        }

        // Drop tokens that reference a skipped token, until no dangling references are left
        do {
            $available = array_flip(array_map(static fn (array $entry): string => implode('.', $entry['path']), $entries));
            $resolvable = [];

            foreach ($entries as $entry) {
                preg_match_all(Dtcg::REFERENCE_REGEX, Json::encode($entry['token']['$value'], false), $matches);
                $isResolvable = !array_diff_key(array_flip($matches[1]), $available);

                if ($isResolvable) {
                    $resolvable[] = $entry;
                } else {
                    $skipped[] = $entry['name'];
                }
            }

            $hasDanglingReferences = count($resolvable) !== count($entries);
            $entries = $resolvable;
        } while ($hasDanglingReferences);

        $tokens = [];

        foreach ($entries as $entry) {
            if (!self::setToken($tokens, $entry['path'], $entry['token'])) {
                $skipped[] = $entry['name'];
            }
        }

        return ['tokens' => $tokens, 'skipped' => $skipped];
    }

    /**
     * Creates a Design Tokens Resolver 2025.10 JSON document from a map of CSS props.
     * All tokens go in a single set.
     *
     * @source https://www.designtokens.org/tr/2025.10/resolver/
     * @param array<string|int, mixed> $props
     * @param string $name - name of the resolver and its token set
     * @param string $description
     * @param (callable(string): void)|null $onWarning - receives the list of skipped tokens, defaults to `error_log()`
     * @return string
     */
    public static function build(
        array $props,
        string $name = 'Design Tokens',
        string $description = '',
        ?callable $onWarning = null,
    ): string {
        ['tokens' => $tokens, 'skipped' => $skipped] = self::tokens($props);

        if ($skipped) {
            $names = implode(', ', array_map(static fn (string $skippedName): string => "--{$skippedName}", $skipped));
            ($onWarning ?? 'error_log')(
                sprintf(
                    "Skipped %d token(s) that the Design Tokens %s format can't express: %s",
                    count($skipped),
                    Dtcg::VERSION,
                    $names,
                ),
            );
        }

        $resolver = ['version' => Dtcg::VERSION, 'name' => $name];

        if ($description !== '') {
            $resolver['description'] = $description;
        }

        $resolver['resolutionOrder'] = new ValueList([
            ['type' => 'set', 'name' => $name, 'sources' => new ValueList([$tokens])],
        ]);

        return Json::encode($resolver);
    }
}
