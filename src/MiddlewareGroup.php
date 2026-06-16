<?php

namespace Componenta\Http\Middleware;

use Componenta\Arrayable\Arrayable;

/**
 * Container for middleware definitions that can be converted to Pipeline.
 *
 * A group must contain at least one middleware definition and cannot be empty.
 */
final class MiddlewareGroup implements \Countable, \IteratorAggregate, Arrayable
{
    /**
     * Array of middleware definitions.
     */
    private(set) array $middlewares = [];

    /**
     * Creates a new middleware group from definitions.
     *
     * @param mixed $middleware First middleware definition (required)
     * @param mixed ...$other Additional middleware definitions
     */
    public function __construct(
        mixed $middleware,
        mixed ...$other
    ) {
        $this->middlewares = [$middleware, ...$other];
    }

    /**
     * Returns a new instance with the added middleware definition.
     *
     * @param mixed $middleware Middleware definition to add
     * @param bool $prepend Whether to add at the beginning of the group
     * @return self New instance with the added middleware
     */
    public function with(mixed $middleware, bool $prepend = false): self
    {
        $middlewares = $this->middlewares;

        if ($prepend) {
            array_unshift($middlewares, $middleware);
        } else {
            $middlewares[] = $middleware;
        }

        return self::fromArray($middlewares);
    }

    /**
     * Returns a new instance with the added middleware definitions.
     *
     * @param iterable $middlewares Set of middleware definitions (must not be empty)
     * @param bool $prepend Whether to add at the beginning of the group
     * @return self New instance with the added middlewares
     * @throws \InvalidArgumentException If no middleware definitions provided
     */
    public function withMany(iterable $middlewares, bool $prepend = false): self
    {
        $middlewareArray = [];
        foreach ($middlewares as $middleware) {
            $middlewareArray[] = $middleware;
        }

        if (empty($middlewareArray)) {
            throw new \InvalidArgumentException(
                'Cannot add empty middleware collection. At least one middleware definition is required.'
            );
        }

        $result = $this->middlewares;

        if ($prepend) {
            foreach (array_reverse($middlewareArray) as $middleware) {
                array_unshift($result, $middleware);
            }
        } else {
            foreach ($middlewareArray as $middleware) {
                $result[] = $middleware;
            }
        }

        return self::fromArray($result);
    }

    /**
     * Returns the number of middleware in the group.
     *
     * @return int Number of middleware definitions (always >= 1)
     */
    public function count(): int
    {
        return count($this->middlewares);
    }

    /**
     * Returns an iterator for traversing middleware definitions.
     *
     * @return \ArrayIterator Iterator for middleware definitions
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->middlewares);
    }

    /**
     * Creates a middleware group from an array of middleware definitions.
     *
     * @param array $middlewares Array of middleware definitions (must not be empty)
     * @return self New middleware group instance
     * @throws \InvalidArgumentException If array is empty
     */
    public static function fromArray(array $middlewares): self
    {
        if (empty($middlewares)) {
            throw new \InvalidArgumentException(
                'MiddlewareGroup cannot be empty. At least one middleware definition is required.'
            );
        }

        $first = array_shift($middlewares);
        return new self($first, ...$middlewares);
    }

    public function toArray(): array
    {
        return $this->middlewares;
    }
}