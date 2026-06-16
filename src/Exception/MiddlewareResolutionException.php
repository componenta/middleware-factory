<?php

namespace Componenta\Http\Middleware\Exception;

/**
 * Exception thrown when middleware cannot be resolved to a valid PSR-15 MiddlewareInterface.
 *
 * This exception is thrown in two scenarios:
 * 1. No registered strategy can handle the middleware definition (all return null)
 * 2. A strategy attempted to create middleware but encountered an error
 */
class MiddlewareResolutionException extends \RuntimeException implements MiddlewareResolutionExceptionInterface
{
    /**
     * Creates a new middleware resolution exception.
     *
     * @param mixed $middleware The middleware definition that could not be resolved
     * @param string $message Optional custom error message. If empty, generates a default message
     * @param \Throwable|null $previous Previous exception in the chain, if any
     */
    public function __construct(
        public readonly mixed $middleware,
        string $message = "",
        ?\Throwable $previous = null
    ) {
        if (empty($message)) {
            $message = sprintf(
                'Failed to resolve middleware of type: %s',
                get_debug_type($middleware)
            );
        }

        parent::__construct($message, $previous?->getCode() ?? 0, $previous);
    }

    /**
     * Factory method: Creates exception wrapping a previous exception.
     *
     * Used when a strategy throws an exception during middleware creation.
     *
     * @param mixed $middleware The middleware definition
     * @param \Throwable $previous The underlying exception
     * @return self New exception instance
     */
    public static function fromPrevious(mixed $middleware, \Throwable $previous): self
    {
        return new self(
            middleware: $middleware,
            message: sprintf(
                'Failed to resolve middleware of type %s: %s',
                get_debug_type($middleware),
                $previous->getMessage()
            ),
            previous: $previous
        );
    }
}