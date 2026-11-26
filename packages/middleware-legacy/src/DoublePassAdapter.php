<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Legacy;

use Closure;
use Componenta\Http\Middleware\Legacy\Internal\NativeCallable;
use LogicException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;

/**
 * Explicit adapter for the Componenta legacy (request, response, next)
 * convention. A PSR-15 continuation cannot transport a seed response.
 */
final readonly class DoublePassAdapter implements MiddlewareInterface
{
    private Closure $callback;

    /**
     * @param callable|array<array-key, mixed> $callback
     */
    public function __construct(
        callable|array $callback,
        private ResponseFactoryInterface $responseFactory,
    ) {
        $this->callback = NativeCallable::capture($callback);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = ($this->callback)(
            $request,
            $this->responseFactory->createResponse(),
            static function (ServerRequestInterface $nextRequest, mixed ...$unsupported) use ($handler): ResponseInterface {
                if ($unsupported !== []) {
                    throw new LogicException('PSR-15 cannot forward a seed response. Call next($request) and modify the returned response.');
                }

                return $handler->handle($nextRequest);
            },
        );

        if (!$response instanceof ResponseInterface) {
            throw new UnexpectedValueException(sprintf(
                'Double-pass middleware must return ResponseInterface; got %s.',
                get_debug_type($response),
            ));
        }

        return $response;
    }
}
