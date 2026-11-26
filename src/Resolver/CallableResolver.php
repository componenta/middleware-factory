<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Resolver;

use Closure;
use Componenta\DI\CallableExecutorInterface;
use Componenta\Http\Middleware\Adapter\CallableAdapter;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Throwable;

/** Adapts callable definitions with DI-aware invocation. */
final readonly class CallableResolver implements MiddlewareResolverInterface
{
    /** @param (Closure(mixed): ResponseInterface)|null $normalizer */
    public function __construct(
        private CallableExecutorInterface $executor,
        private ?Closure $normalizer = null,
    ) {}

    public function resolve(mixed $middleware): ?MiddlewareInterface
    {
        if (!is_string($middleware) && !is_array($middleware) && !is_callable($middleware)) {
            return null;
        }
        if (is_array($middleware)
            && (count($middleware) !== 2 || !array_key_exists(0, $middleware) || !array_key_exists(1, $middleware)
                || !(is_string($middleware[0]) || is_object($middleware[0]))
                || !is_string($middleware[1]))
        ) {
            return null;
        }

        try {
            return $this->adapt($this->executor->resolve($middleware));
        } catch (Throwable $e) {
            throw MiddlewareResolutionException::fromPrevious($middleware, $e);
        }
    }

    /** Adapts a native callable without another service lookup. Array slots are captured before validation. */
    public function adapt(callable|array $callable): MiddlewareInterface
    {
        return CallableAdapter::fromCallable($callable, $this->executor, $this->normalizer);
    }
}
