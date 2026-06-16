<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Resolver;

use Componenta\Http\Middleware\Adapter\RequestHandlerAdapter;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolver for middleware defined as fully-qualified class names.
 *
 * Resolves middleware from string class names by retrieving instances from a PSR-11 container.
 * Supports both MiddlewareInterface and RequestHandlerInterface implementations.
 */
final readonly class ClassNameResolver implements MiddlewareResolverInterface
{
    /**
     * Creates a new class name resolver.
     *
     * @param ContainerInterface $container PSR-11 container for resolving class instances
     */
    public function __construct(
        private ContainerInterface $container
    ) {
    }

    /**
     * Resolves middleware from a class name string.
     *
     * @param mixed $middleware The middleware definition (expected to be a class name string)
     * @return MiddlewareInterface|null Resolved middleware instance or null if not a string
     * @throws MiddlewareResolutionExceptionInterface If resolution fails
     */
    public function resolve(mixed $middleware): ?MiddlewareInterface
    {
        return match (true) {
            !is_string($middleware) => null,
            ($obj = $this->get($middleware, MiddlewareInterface::class)) !== null => $obj,
            ($obj = $this->get($middleware, RequestHandlerInterface::class)) !== null => new RequestHandlerAdapter($obj),
            default => null
        };
    }

    /**
     * Retrieves an instance from the container if the class implements the specified interface.
     *
     * @param string $middleware The fully-qualified class name
     * @param string $interface The required interface or parent class
     * @return MiddlewareInterface|RequestHandlerInterface|null Container instance if valid, null otherwise
     * @throws MiddlewareResolutionException If container fails or returns invalid instance
     */
    private function get(string $middleware, string $interface): null|MiddlewareInterface|RequestHandlerInterface
    {
        if (!is_subclass_of($middleware, $interface) || !$this->container->has($middleware)) {
            return null;
        }

        try {
            $instance = $this->container->get($middleware);
        } catch (\Throwable $e) {
            throw MiddlewareResolutionException::fromPrevious($middleware, $e);
        }

        // Verify container returned correct type
        if (!$instance instanceof $interface) {
            throw new MiddlewareResolutionException(
                middleware: $middleware,
                message: sprintf(
                    "The container returned an instance of type '%s' for class '%s', but it must implement '%s'. " .
                    "Check your container configuration and ensure the class implements the required interface.",
                    get_debug_type($instance),
                    $middleware,
                    $interface
                )
            );
        }

        return $instance;
    }

}