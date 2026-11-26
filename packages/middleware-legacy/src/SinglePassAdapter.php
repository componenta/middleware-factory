<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Legacy;

use Closure;
use Componenta\Http\Middleware\Legacy\Internal\NativeCallable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;

/** Explicit adapter for a legacy (request, next) callable. */
final readonly class SinglePassAdapter implements MiddlewareInterface
{
    private Closure $callback;

    /**
     * @param callable|array<array-key, mixed> $callback
     */
    public function __construct(callable|array $callback)
    {
        $this->callback = NativeCallable::capture($callback);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = ($this->callback)(
            $request,
            static fn (ServerRequestInterface $nextRequest): ResponseInterface => $handler->handle($nextRequest),
        );

        if (!$response instanceof ResponseInterface) {
            throw new UnexpectedValueException(sprintf(
                'Single-pass middleware must return ResponseInterface; got %s.',
                get_debug_type($response),
            ));
        }

        return $response;
    }
}
