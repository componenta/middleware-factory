<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Legacy\Internal;

use Closure;
use InvalidArgumentException;

/** @internal */
final class NativeCallable
{
    /**
     * Capture a native callable, detaching referenced array slots before
     * validation can execute an autoloader.
     *
     * @param callable|array<array-key, mixed> $callback
     */
    public static function capture(callable|array $callback): Closure
    {
        if ($callback instanceof Closure) {
            return $callback;
        }

        if (is_array($callback)) {
            $snapshot = [];
            foreach ($callback as $key => $value) {
                $snapshot[$key] = $value;
            }
            $callback = $snapshot;
        }

        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Invalid legacy middleware callable.');
        }

        return Closure::fromCallable($callback);
    }
}
