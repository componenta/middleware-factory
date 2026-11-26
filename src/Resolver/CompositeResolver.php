<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Resolver;

use Psr\Http\Server\MiddlewareInterface;

/** Immutable, ordered collection of extension resolvers. */
final readonly class CompositeResolver implements MiddlewareResolverInterface
{
    /** @var list<MiddlewareResolverInterface> */
    private array $resolvers;

    public function __construct(MiddlewareResolverInterface ...$resolvers)
    {
        $this->resolvers = array_values($resolvers);
    }

    public function resolve(mixed $middleware): ?MiddlewareInterface
    {
        foreach ($this->resolvers as $resolver) {
            $resolved = $resolver->resolve($middleware);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /** Returns a new chain. The original chain is not modified. */
    public function with(MiddlewareResolverInterface $resolver, bool $prepend = false): self
    {
        return new self(...($prepend
            ? [$resolver, ...$this->resolvers]
            : [...$this->resolvers, $resolver]));
    }

    /** @param iterable<MiddlewareResolverInterface> $resolvers */
    public static function fromArray(iterable $resolvers): self
    {
        $items = [];
        foreach ($resolvers as $resolver) {
            $items[] = $resolver;
        }

        return new self(...$items);
    }
}
