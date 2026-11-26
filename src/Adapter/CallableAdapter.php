<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Adapter;

use Closure;
use Componenta\DI\CallableExecutorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use InvalidArgumentException;
use UnexpectedValueException;

/** Adapts a resolved callable to PSR-15 using DI parameter resolution. */
final readonly class CallableAdapter implements MiddlewareInterface
{
    /** @var callable */
    private mixed $callable;

    /**
     * @param callable|array<array-key, mixed> $callable
     * @param (Closure(mixed): ResponseInterface)|null $normalizer
     */
    private function __construct(
        callable|array $callable,
        private CallableExecutorInterface $invoker,
        private ?Closure $normalizer = null,
    ) {
        // A resolved function name must not be interpreted as a service id again.
        // Native method pairs keep DI's named-method parameter preparation path.
        if (is_string($callable)) {
            $callable = str_contains($callable, '::')
                ? explode('::', $callable, 2)
                : Closure::fromCallable($callable);
        } elseif (is_array($callable)) {
            if (count($callable) !== 2
                || !array_key_exists(0, $callable)
                || !array_key_exists(1, $callable)
            ) {
                throw new InvalidArgumentException('Invalid middleware callable pair.');
            }

            // PHP callable validation can invoke an autoloader. The array arm
            // of the parameter type lets us detach reference slots before it does.
            $callable = [$callable[0], $callable[1]];
        }
        if (!self::isPublicCallable($callable)) {
            throw new InvalidArgumentException('Resolved middleware target is not callable.');
        }
        $this->callable = $callable;
    }

    /** @phpstan-assert-if-true callable $value */
    private static function isPublicCallable(mixed $value): bool
    {
        // The adapter's own private methods must not become valid callbacks
        // just because validation happens inside this class.
        static $check = null;
        $check ??= Closure::bind(static fn (mixed $candidate): bool => is_callable($candidate), null, null);

        return $check !== null && $check($value);
    }

    /**
     * @param callable|array<array-key, mixed> $callable
     * @param (Closure(mixed): ResponseInterface)|null $normalizer
     */
    public static function fromCallable(
        callable|array $callable,
        CallableExecutorInterface $invoker,
        ?Closure $normalizer = null,
    ): self {
        return new self($callable, $invoker, $normalizer);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $result = $this->invoker->call($this->callable, [
            ServerRequestInterface::class => $request,
            RequestHandlerInterface::class => $handler,
        ]);

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if ($result instanceof MiddlewareInterface) {
            if ($result === $this) {
                throw new UnexpectedValueException('Callable returned its own middleware adapter.');
            }

            return $result->process($request, $handler);
        }

        if ($result instanceof RequestHandlerInterface) {
            return $result->handle($request);
        }

        if ($this->normalizer !== null) {
            return ($this->normalizer)($result);
        }

        throw new UnexpectedValueException(sprintf(
            'Callable middleware must return a response, middleware or request handler; got %s.',
            get_debug_type($result),
        ));
    }
}
