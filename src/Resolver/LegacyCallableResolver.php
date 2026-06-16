<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Resolver;

use Componenta\DI\CallableExecutorInterface;
use Componenta\Http\Middleware\Adapter\CallableAdapter;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Componenta\Reflection\Reflection;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Resolver for legacy callable middleware patterns.
 *
 * Detects and adapts two legacy middleware signatures:
 *
 * - **Single-pass**: `function(ServerRequestInterface $request, callable $next)`
 * - **Double-pass**: `function(ServerRequestInterface $request, ResponseInterface $response, callable $next)`
 *
 * This resolver is NOT included in the default resolver chain.
 * Register it via config if legacy middleware support is needed:
 *
 * ```php
 * ConfigKey::RESOLVERS => [LegacyCallableResolver::class],
 * ```
 */
final readonly class LegacyCallableResolver implements MiddlewareResolverInterface
{
    public function __construct(
        private CallableExecutorInterface $executor,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function resolve(mixed $middleware): ?MiddlewareInterface
    {
        try {
            $callable = $this->executor->resolve($middleware);
        } catch (\Throwable) {
            return null;
        }

        if (!$callable) {
            return null;
        }

        try {
            $parameters = Reflection::callable($callable)->getParameters();
            $parameterCount = count($parameters);

            if ($parameterCount < 2
                || !$this->isParameterTypeCompatible($parameters[0], ServerRequestInterface::class)
            ) {
                return null;
            }

            if ($this->isSinglePassMiddleware($parameterCount, $parameters)) {
                return CallableAdapter::singlePassMiddleware($callable, $this->executor);
            }

            if ($this->isDoublePassMiddleware($parameterCount, $parameters)) {
                return CallableAdapter::doublePassMiddleware($callable, $this->executor, $this->responseFactory);
            }
        } catch (\Throwable $e) {
            throw new MiddlewareResolutionException(
                middleware: $middleware,
                message: sprintf(
                    'Failed to analyze callable signature: %s',
                    $e->getMessage(),
                ),
                previous: $e,
            );
        }

        return null;
    }

    private function isSinglePassMiddleware(int $parameterCount, array $parameters): bool
    {
        return $parameterCount === 2 && $this->declaresCallable($parameters[1]);
    }

    private function isDoublePassMiddleware(int $parameterCount, array $parameters): bool
    {
        return $parameterCount === 3
            && $this->isParameterTypeCompatible($parameters[1], ResponseInterface::class)
            && $this->declaresCallable($parameters[2]);
    }

    private function isParameterTypeCompatible(ReflectionParameter $parameter, string $expectedType): bool
    {
        $reflectionType = $parameter->getType();

        if (!$reflectionType instanceof ReflectionNamedType) {
            return false;
        }

        $actualTypeName = $reflectionType->getName();

        return $actualTypeName === $expectedType || is_subclass_of($actualTypeName, $expectedType);
    }

    private function declaresCallable(ReflectionParameter $parameter): bool
    {
        $reflectionType = $parameter->getType();

        if (!$reflectionType) {
            return false;
        }

        if ($reflectionType instanceof \ReflectionUnionType) {
            return array_any(
                $reflectionType->getTypes(),
                static fn($type) => $type instanceof ReflectionNamedType && $type->getName() === 'callable',
            );
        }

        if ($reflectionType instanceof ReflectionNamedType) {
            return $reflectionType->getName() === 'callable';
        }

        return false;
    }
}
