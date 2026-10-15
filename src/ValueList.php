<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder;

/**
 * A list value, such as the layers of a shadow or a font stack.
 *
 * PHP arrays are both lists and maps, so a token scale keyed `0`, `1`, `2`
 * would be indistinguishable from a list. Internally every PHP array is a map
 * and every list is a `ValueList`, the same split as JSON objects and arrays.
 *
 * @implements \IteratorAggregate<int, mixed>
 */
final class ValueList implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(public readonly array $items)
    {
    }

    /**
     * @return \ArrayIterator<int, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return list<mixed>
     */
    public function jsonSerialize(): array
    {
        return Json::prepare($this->items);
    }

    /**
     * Applies `$callback` to every item.
     *
     * @param callable(mixed): mixed $callback
     * @return self
     */
    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    /**
     * Joins the items as CSS, the same way a JavaScript array is joined.
     *
     * @param string $separator
     * @return string
     */
    public function join(string $separator): string
    {
        return implode($separator, array_map([Utils::class, 'toString'], $this->items));
    }
}
