<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use ArrayIterator;
use Componenta\Arrayable\Arrayable;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;

/**
 * Non-empty, immutable ordered collection of middleware definitions.
 * Definition objects are shared; array definition slots are detached from caller references.
 * Collection operations never mutate the list; nested custom payloads are not deep-frozen.
 *
 * @implements IteratorAggregate<int, mixed>
 */
final readonly class MiddlewareGroup implements Countable, IteratorAggregate, Arrayable
{
    /** @var non-empty-list<mixed> */
    private array $middlewares;

    public function __construct(mixed $middleware, mixed ...$other)
    {
        $items = [self::snapshotDefinition($middleware)];
        foreach ($other as $definition) {
            $items[] = self::snapshotDefinition($definition);
        }
        $this->middlewares = $items;
    }

    public function with(mixed $middleware, bool $prepend = false): self
    {
        return self::fromArray($prepend
            ? [$middleware, ...$this->middlewares]
            : [...$this->middlewares, $middleware]);
    }

    /** @param iterable<mixed> $middlewares */
    public function withMany(iterable $middlewares, bool $prepend = false): self
    {
        $items = [];
        foreach ($middlewares as $middleware) {
            $items[] = self::snapshotDefinition($middleware);
        }
        if ($items === []) {
            throw new InvalidArgumentException('Cannot add an empty middleware collection.');
        }

        return self::fromArray($prepend
            ? [...$items, ...$this->middlewares]
            : [...$this->middlewares, ...$items]);
    }

    private static function snapshotDefinition(mixed $definition): mixed
    {
        if (!is_array($definition)) {
            return $definition;
        }

        // Detach callable receiver and method slots before a caller (or a
        // resumed generator) can change their referenced values.
        $snapshot = [];
        foreach ($definition as $key => $value) {
            $snapshot[$key] = $value;
        }

        return $snapshot;
    }

    public function count(): int
    {
        return count($this->middlewares);
    }

    /** @return ArrayIterator<int, mixed> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->middlewares);
    }

    /** @param array<array-key, mixed> $middlewares */
    public static function fromArray(array $middlewares): self
    {
        if ($middlewares === []) {
            throw new InvalidArgumentException('MiddlewareGroup cannot be empty.');
        }

        return new self(...array_values($middlewares));
    }

    /** @return non-empty-list<mixed> */
    public function toArray(): array
    {
        return $this->middlewares;
    }
}
