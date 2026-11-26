<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Legacy\Resolver;

use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;
use Componenta\Http\Middleware\Legacy\Internal\LegacySignature;
use Componenta\Http\Middleware\Legacy\DoublePassAdapter;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Throwable;

/** Optional Middleware Factory 3 integration for native double-pass definitions. */
final readonly class DoublePassResolver implements MiddlewareResolverInterface
{
    public function __construct(
        private ContainerInterface $container,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function resolve(mixed $middleware): ?MiddlewareInterface
    {
        try {
            if (is_string($middleware) && $this->container->has($middleware)) {
                return null;
            }

            $callback = LegacySignature::recognize($middleware, true);

            return $callback === null ? null : new DoublePassAdapter($callback, $this->responseFactory);
        } catch (MiddlewareResolutionExceptionInterface $error) {
            throw $error;
        } catch (Throwable $error) {
            throw MiddlewareResolutionException::fromPrevious($middleware, $error);
        }
    }
}
