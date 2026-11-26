<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Exception;

/** Failure to resolve a middleware definition. Raw definitions are not exposed. */
interface MiddlewareResolutionExceptionInterface extends \Throwable
{
    public string $definitionType { get; }
}
