<?php

namespace Componenta\Http\Middleware\Exception;

/**
 * Interface for exceptions related to middleware resolution failures.
 *
 * Implementing classes should expose the middleware definition that failed to resolve.
 */
interface MiddlewareResolutionExceptionInterface extends \Throwable
{
    /**
     * The middleware definition that could not be resolved.
     *
     * @var mixed
     */
    public mixed $middleware { get; }
}