<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Resolver;

use Componenta\DI\CallableExecutorInterface;
use Componenta\Http\Middleware\Adapter\CallableAdapter;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Componenta\Http\Responder;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Resolver for callable middleware with dependency injection.
 *
 * Resolves any value that can be converted to a PHP callable
 * and wraps it in a standard {@see CallableAdapter} with DI support.
 */
final readonly class CallableResolver implements MiddlewareResolverInterface
{
    public function __construct(
        private CallableExecutorInterface $executor,
        private ?Responder $responder = null,
    ) {}

    public function resolve(mixed $middleware): ?MiddlewareInterface
    {
        try {
            $callable = $this->executor->resolve($middleware);
        } catch (\Throwable $e) {
            throw MiddlewareResolutionException::fromPrevious($middleware, $e);
        }

        if (!$callable) {
            return null;
        }

        return new CallableAdapter($callable, $this->executor, $this->responder);
    }
}
