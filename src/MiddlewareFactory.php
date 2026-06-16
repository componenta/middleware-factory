<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Componenta\Http\Middleware\Adapter\RequestHandlerAdapter;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;

/**
 * Factory for creating PSR-15 middleware instances from various definitions.
 *
 * This factory delegates middleware resolution to a resolver and handles
 * built-in PSR-15 types (MiddlewareInterface, RequestHandlerInterface).
 */
final readonly class MiddlewareFactory
{
    /**
     * Creates a new middleware factory.
     *
     * @param MiddlewareResolverInterface $resolver Middleware resolver
     */
    public function __construct(
        private MiddlewareResolverInterface $resolver
    ) {
        // Inject factory into aware resolvers
        if ($resolver instanceof MiddlewareFactoryAwareInterface) {
            $resolver->setMiddlewareFactory($this);
        }
    }

    /**
     * Resolves a middleware definition to a valid PSR-15 MiddlewareInterface instance.
     *
     * @param mixed $middleware The middleware definition to resolve
     * @return MiddlewareInterface A valid PSR-15 middleware instance
     * @throws MiddlewareResolutionExceptionInterface If resolution fails
     */
    public function createMiddleware(mixed $middleware): MiddlewareInterface
    {
        // Try resolver
        try {
            $resolved = $this->resolver->resolve($middleware);

            if ($resolved !== null) {
                return $resolved;
            }
        } catch (\Throwable $e) {
            if (!$e instanceof MiddlewareResolutionExceptionInterface) {
                throw MiddlewareResolutionException::fromPrevious($middleware, $e);
            }
            throw $e;
        }

        // Handle built-in PSR-15 types
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if ($middleware instanceof RequestHandlerInterface) {
            return new RequestHandlerAdapter($middleware);
        }

        // Resolution failed
        throw new MiddlewareResolutionException($middleware);
    }
}