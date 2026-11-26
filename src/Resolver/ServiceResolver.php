<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Resolver;

use Closure;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Throwable;

/**
 * Resolves opaque PSR-11 identifiers. A present entry is authoritative:
 * failures never fall through to another interpretation of that identifier.
 */
final readonly class ServiceResolver
{
    public function __construct(private ContainerInterface $container) {}

    /**
     * @param Closure(mixed): MiddlewareInterface $adapt
     */
    public function resolve(string $id, Closure $adapt): ?MiddlewareInterface
    {
        try {
            if (!$this->container->has($id)) {
                return null;
            }

            return $adapt($this->container->get($id));
        } catch (Throwable $e) {
            throw MiddlewareResolutionException::fromPrevious($id, $e);
        }
    }
}
