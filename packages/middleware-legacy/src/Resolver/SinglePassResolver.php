<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Legacy\Resolver;

use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;
use Componenta\Http\Middleware\Legacy\Internal\LegacySignature;
use Componenta\Http\Middleware\Legacy\SinglePassAdapter;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Throwable;

/** Optional Middleware Factory 3 integration for native single-pass definitions. */
final readonly class SinglePassResolver implements MiddlewareResolverInterface
{
    public function __construct(private ContainerInterface $container) {}

    public function resolve(mixed $middleware): ?MiddlewareInterface
    {
        try {
            // The factory resolves present service identifiers itself.
            if (is_string($middleware) && $this->container->has($middleware)) {
                return null;
            }

            $callback = LegacySignature::recognize($middleware, false);

            return $callback === null ? null : new SinglePassAdapter($callback);
        } catch (MiddlewareResolutionExceptionInterface $error) {
            throw $error;
        } catch (Throwable $error) {
            throw MiddlewareResolutionException::fromPrevious($middleware, $error);
        }
    }
}
