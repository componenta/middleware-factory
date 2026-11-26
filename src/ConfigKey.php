<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

/** Configuration keys for the middleware factory. */
final class ConfigKey extends \Componenta\Config\ConfigKey
{
    /** @var list<non-empty-string> Extension resolver service identifiers, in priority order. */
    public const string RESOLVERS = 'Componenta\Http\Middleware::RESOLVERS';
}
