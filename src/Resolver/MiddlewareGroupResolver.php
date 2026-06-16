<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Resolver;

use Componenta\Http\Middleware\EmptyPipelineHandler;
use Componenta\Http\Middleware\MiddlewareFactoryAwareInterface;
use Componenta\Http\Middleware\Pipeline;
use Psr\Http\Server\MiddlewareInterface;
use Componenta\Http\Middleware\MiddlewareGroup;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;

/**
 * Resolver for MiddlewareGroup definitions.
 *
 * Converts MiddlewareGroup into a Pipeline by resolving all contained middleware definitions
 * using the injected MiddlewareFactory.
 */
final class MiddlewareGroupResolver implements MiddlewareResolverInterface, MiddlewareFactoryAwareInterface
{
    /**
     * Middleware factory for resolving group items.
     */
    private ?MiddlewareFactory $factory = null;

    /**
     * Sets the middleware factory instance.
     *
     * @param MiddlewareFactory $factory The middleware factory
     * @return void
     */
    public function setMiddlewareFactory(MiddlewareFactory $factory): void
    {
        $this->factory = $factory;
    }

    /**
     * Resolves MiddlewareGroup into a Pipeline.
     *
     * @param mixed $middleware The middleware definition
     * @return MiddlewareInterface|null Pipeline or null if not a MiddlewareGroup
     * @throws MiddlewareResolutionException If factory not set or resolution fails
     */
    public function resolve(mixed $middleware): ?MiddlewareInterface
    {
        if (!$middleware instanceof MiddlewareGroup) {
            return null;
        }

        if ($this->factory === null) {
            throw new MiddlewareResolutionException(
                middleware: $middleware,
                message: 'MiddlewareFactory not injected into MiddlewareGroupResolver. Ensure the resolver is properly registered with the factory.'
            );
        }

        $resolvedMiddlewares = [];

        foreach ($middleware->middlewares as $index => $definition) {
            try {
                $resolvedMiddlewares[] = $this->factory->createMiddleware($definition);
            } catch (\Throwable $e) {
                throw new MiddlewareResolutionException(
                    middleware: $middleware,
                    message: sprintf(
                        'Failed to resolve middleware at index %d in group: %s',
                        $index,
                        $e->getMessage()
                    ),
                    previous: $e
                );
            }
        }

        return new Pipeline($resolvedMiddlewares, new EmptyPipelineHandler);
    }
}