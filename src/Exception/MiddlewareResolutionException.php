<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Exception;

use RuntimeException;
use Throwable;

final class MiddlewareResolutionException extends RuntimeException implements MiddlewareResolutionExceptionInterface
{
    public readonly string $definitionType;

    public function __construct(
        mixed $middleware,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        $this->definitionType = get_debug_type($middleware);
        $code = $previous?->getCode();
        parent::__construct(
            $message !== '' ? $message : sprintf('Failed to resolve middleware of type: %s', $this->definitionType),
            is_int($code) ? $code : 0,
            $previous,
        );
    }

    public static function fromPrevious(mixed $middleware, Throwable $previous): self
    {
        return new self($middleware, previous: $previous);
    }
}
