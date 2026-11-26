<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

/** Configuration keys for the middleware factory. */
final class ConfigKey extends \Componenta\Config\ConfigKey
{
    /** Configuration value: list<non-empty-string> of extension resolver service identifiers, in priority order. */
    public const string RESOLVERS = 'Componenta\Http\Middleware::RESOLVERS';
}
