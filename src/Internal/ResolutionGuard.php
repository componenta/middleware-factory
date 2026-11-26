<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Internal;

use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Componenta\Http\Middleware\MiddlewareGroup;
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

    /**
     * Standard definitions have stable, non-colliding keys without resolving
     * services, autoloading method owners or serializing application objects.
     */
    public static function keyFor(mixed $definition): ?string
    {
        if (is_string($definition)) {
            return 'identifier:' . $definition;
        }

        if ($definition instanceof MiddlewareGroup) {
            return 'group:' . spl_object_id($definition);
        }

        if (is_object($definition) && is_callable($definition)) {
            return 'callable-object:' . spl_object_id($definition);
        }

        if (is_array($definition) && count($definition) === 2
            && isset($definition[0], $definition[1])
            && is_string($definition[1])
        ) {
            $receiver = $definition[0];
            $method = $definition[1];
            if (is_object($receiver)) {
                return 'method-object:' . spl_object_id($receiver) . ':' . $method;
            }
            if (is_string($receiver)) {
                // A service id may contain delimiters, so encode its length.
                return 'method-string:' . strlen($receiver) . ':' . $receiver . ':' . $method;
            }
        }

        return null;
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
