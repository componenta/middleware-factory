<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Resolver;

use Psr\Http\Server\MiddlewareInterface;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareFactoryAwareInterface;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;

/**
 * Composite resolver that delegates to multiple resolvers in order.
 *
 * Tries each resolver sequentially until one successfully resolves the middleware.
 * Returns null only if all resolvers return null.
 */
final class CompositeResolver implements MiddlewareResolverInterface, MiddlewareFactoryAwareInterface
{
    /**
     * Collection of resolvers.
     *
     * @var array<MiddlewareResolverInterface>
     */
    private array $resolvers = [];

    /**
     * Creates a new composite resolver.
     *
     * @param MiddlewareResolverInterface ...$resolvers Resolvers in priority order
     */
    public function __construct(
        MiddlewareResolverInterface ...$resolvers
    ) {
        $this->resolvers = $resolvers;
    }

    /**
     * Sets the middleware factory and propagates it to child resolvers.
     *
     * @param MiddlewareFactory $factory The middleware factory
     * @return void
     */
    public function setMiddlewareFactory(MiddlewareFactory $factory): void
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver instanceof MiddlewareFactoryAwareInterface) {
                $resolver->setMiddlewareFactory($factory);
            }
        }
    }

    /**
     * Attempts to resolve middleware using registered resolvers.
     *
     * Tries each resolver in order until one returns a non-null result.
     * If a resolver throws an exception, it's propagated immediately.
     *
     * @param mixed $middleware The middleware definition to resolve
     * @return MiddlewareInterface|null Resolved middleware, or null if no resolver can handle it
     * @throws MiddlewareResolutionExceptionInterface If resolution fails
     */
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

    /**
     * Adds a resolver to the composite.
     *
     * @param MiddlewareResolverInterface $resolver Resolver to add
     * @param bool $prepend Whether to add at the beginning (higher priority)
     * @return self Returns the current instance for method chaining
     */
    public function add(MiddlewareResolverInterface $resolver, bool $prepend = false): self
    {
        $prepend ? array_unshift($this->resolvers, $resolver) :
            $this->resolvers[] = $resolver;

        return $this;
    }

    /**
     * Creates a composite resolver from an array of resolvers.
     *
     * @param array<MiddlewareResolverInterface> $resolvers Array of resolvers
     * @return self New composite resolver instance
     */
    public static function fromArray(array $resolvers): self
    {
        return new self(...$resolvers);
    }
}