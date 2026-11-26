<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Internal;

use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Fiber;
use stdClass;
use WeakMap;

/**
 * Tracks active definitions for one factory and execution context.
 * Does not retain completed definitions or execution contexts.
 *
 * @internal
 */
final class ResolutionGuard
{
    /** @var WeakMap<object, array<string, true>> */
    private WeakMap $active;
    private readonly object $main;

    public function __construct()
    {
        $this->active = new WeakMap();
        $this->main = new stdClass();
    }

    public function enter(string $key, mixed $definition): object
    {
        $context = Fiber::getCurrent() ?? $this->main;
        $active = $this->active[$context] ?? [];
        if (isset($active[$key])) {
            throw new MiddlewareResolutionException($definition, 'Cyclic middleware definition.');
        }

        $active[$key] = true;
        $this->active[$context] = $active;

        return $context;
    }

    public function leave(object $context, string $key): void
    {
        $active = $this->active[$context];
        unset($active[$key]);
        if ($active === []) {
            unset($this->active[$context]);
        } else {
            $this->active[$context] = $active;
        }
    }
}
