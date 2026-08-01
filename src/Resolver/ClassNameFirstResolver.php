<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Resolver;

use Closure;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareFactoryAwareInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Resolves the production hot path without materializing the generic resolver graph.
 *
 * The fallback remains fully compatible with groups, callables and custom
 * resolvers, but is created only when a class-name definition cannot be handled.
 */
final class ClassNameFirstResolver implements MiddlewareResolverInterface, MiddlewareFactoryAwareInterface
{
    private readonly ClassNameResolver $classNameResolver;
    private readonly Closure $fallbackFactory;
    private ?MiddlewareResolverInterface $fallback = null;
    private ?MiddlewareFactory $middlewareFactory = null;

    /** @param Closure(): MiddlewareResolverInterface $fallbackFactory */
    public function __construct(ContainerInterface $container, Closure $fallbackFactory)
    {
        $this->classNameResolver = new ClassNameResolver($container);
        $this->fallbackFactory = $fallbackFactory;
    }

    public function resolve(mixed $middleware): ?MiddlewareInterface
    {
        $resolved = $this->classNameResolver->resolve($middleware);

        if ($resolved !== null) {
            return $resolved;
        }

        return $this->fallback()->resolve($middleware);
    }

    public function setMiddlewareFactory(MiddlewareFactory $factory): void
    {
        $this->middlewareFactory = $factory;

        if ($this->fallback instanceof MiddlewareFactoryAwareInterface) {
            $this->fallback->setMiddlewareFactory($factory);
        }
    }

    private function fallback(): MiddlewareResolverInterface
    {
        if ($this->fallback !== null) {
            return $this->fallback;
        }

        $fallback = ($this->fallbackFactory)();

        if ($this->middlewareFactory !== null && $fallback instanceof MiddlewareFactoryAwareInterface) {
            $fallback->setMiddlewareFactory($this->middlewareFactory);
        }

        return $this->fallback = $fallback;
    }
}
