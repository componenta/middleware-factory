<?php

namespace Componenta\Http\Middleware\Resolver;

use Psr\Http\Server\MiddlewareInterface;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;

/**
 * Strategy for resolving middleware definitions into PSR-15 MiddlewareInterface instances.
 *
 * Implementations attempt to convert specific types of middleware definitions
 * (callables, class names, pipelines, etc.) into valid PSR-15 middleware.
 *
 * Contract:
 * - Return MiddlewareInterface if the strategy can handle and successfully resolves the definition
 * - Return null if the strategy cannot handle this type of middleware definition
 * - Throw MiddlewareResolutionExceptionInterface if the strategy can handle the type but resolution fails
 */
interface MiddlewareResolverInterface
{
    /**
     * Attempts to resolve the middleware definition into a PSR-15 middleware instance.
     *
     * @param mixed $middleware The middleware definition to resolve
     * @return MiddlewareInterface|null Resolved middleware, or null if this strategy cannot handle it
     * @throws MiddlewareResolutionExceptionInterface If resolution fails
     */
    public function resolve(mixed $middleware): ?MiddlewareInterface;
}