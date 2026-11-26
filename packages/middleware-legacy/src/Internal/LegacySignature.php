<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Legacy\Internal;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Recognizes only unambiguous, explicitly typed native legacy signatures.
 *
 * @internal
 */
final class LegacySignature
{
    public static function recognize(mixed $definition, bool $doublePass): ?Closure
    {
        if ($definition instanceof MiddlewareInterface || $definition instanceof RequestHandlerInterface) {
            return null;
        }

        // Detach method slots before PHP can invoke application autoloaders.
        if (is_array($definition)) {
            if (count($definition) !== 2 || !isset($definition[0], $definition[1])
                || !(is_string($definition[0]) || is_object($definition[0]))
                || !is_string($definition[1])
            ) {
                return null;
            }
            $definition = [$definition[0], $definition[1]];
        }

        if (!is_callable($definition)) {
            return null;
        }

        $callback = NativeCallable::capture($definition);
        $parameters = (new ReflectionFunction($callback))->getParameters();

        if (count($parameters) !== ($doublePass ? 3 : 2)
            || !self::hasType($parameters[0], ServerRequestInterface::class)
        ) {
            return null;
        }

        foreach ($parameters as $parameter) {
            if ($parameter->isPassedByReference() || $parameter->isVariadic()) {
                return null;
            }
        }

        if ($doublePass && !self::hasType($parameters[1], ResponseInterface::class)) {
            return null;
        }

        $next = $parameters[$doublePass ? 2 : 1];

        return self::hasType($next, 'callable') || self::hasType($next, Closure::class)
            ? $callback
            : null;
    }

    private static function hasType(ReflectionParameter $parameter, string $expected): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && $type->getName() === $expected;
    }
}
