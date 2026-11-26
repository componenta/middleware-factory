<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Resolver;

use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Extension for resolving middleware definitions.
 * Return null only for definitions this resolver does not handle.
 * Once a definition is claimed, resolution failures must not fall through.
 */
interface MiddlewareResolverInterface
{
    /** @throws MiddlewareResolutionExceptionInterface */
    public function resolve(mixed $middleware): ?MiddlewareInterface;
}
