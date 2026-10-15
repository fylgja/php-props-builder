<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder\Formats;

/**
 * Google Stitch exports a design system as a Markdown file with a YAML
 * frontmatter block. The Markdown body is prose (brand voice, component
 * notes, ...) and is not part of the Design Tokens, so it's ignored.
 */
final class Stitch
{
    /**
     * Every top-level frontmatter key is treated as a Design Token group,
     * except for these known metadata keys, which are dropped.
     * Extend this list as more Stitch exports are seen in the wild.
     */
    public const METADATA_KEYS = ['name', 'description'];

    /**
     * Strips the quotes off a scalar YAML value, if present.
     *
     * @param string $raw
     * @return string
     */
    private static function parseYamlScalar(string $raw): string
    {
        $value = trim($raw);
        $isQuoted = strlen($value) > 1
            && ($value[0] === "'" || $value[0] === '"')
            && $value[-1] === $value[0];

        return $isQuoted ? substr($value, 1, -1) : $value;
    }

    /**
     * Parses a minimal, indentation-based subset of YAML: nested maps of
     * `key: value` pairs. Lists, anchors, multi-line strings, and other
     * advanced YAML features are not supported.
     *
     * @param string $yaml
     * @return array<string|int, mixed>
     */
    private static function parseYaml(string $yaml): array
    {
        $root = [];
        $stack = [['indent' => -1, 'node' => &$root]];

        foreach (explode("\n", $yaml) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $separatorIndex = strpos($line, ':');
            if ($separatorIndex === false) {
                continue;
            }

            $indent = strlen($line) - strlen(ltrim($line));
            $key = trim(substr($line, 0, $separatorIndex));
            $rawValue = trim(substr($line, $separatorIndex + 1));

            while (count($stack) > 1 && $indent <= $stack[count($stack) - 1]['indent']) {
                array_pop($stack);
            }

            $parent = &$stack[count($stack) - 1]['node'];

            if ($rawValue === '') {
                $parent[$key] = [];
                $stack[] = ['indent' => $indent, 'node' => &$parent[$key]];
            } else {
                $parent[$key] = self::parseYamlScalar($rawValue);
            }

            unset($parent);
        }

        return $root;
    }

    /**
     * Converts a Google Stitch Markdown export into a plain token map.
     *
     * Stitch exports the color group as `colors`, but Hyvä's naming convention
     * is the singular `color`. It's renamed here so `--colors-primary` becomes
     * `--color-primary` without requiring a manual `rename` config. If the
     * frontmatter already uses `color`, it's left as-is.
     *
     * @param string $markdown - the raw Markdown file content
     * @return array<string|int, mixed>
     */
    public static function parse(string $markdown): array
    {
        if (!preg_match('/^---\r?\n([\s\S]*?)\r?\n---/', $markdown, $match)) {
            return [];
        }

        $tokens = array_diff_key(self::parseYaml($match[1]), array_flip(self::METADATA_KEYS));

        if (array_key_exists('colors', $tokens)) {
            if (!array_key_exists('color', $tokens)) {
                $tokens['color'] = $tokens['colors'];
            }
            unset($tokens['colors']);
        }

        return $tokens;
    }
}
