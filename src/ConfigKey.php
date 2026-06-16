<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

/**
 * Configuration keys for the middleware factory library.
 */
final class ConfigKey extends \Componenta\Config\ConfigKey
{
    /**
     * Additional middleware resolvers to prepend to the default chain.
     *
     * @var list<class-string<Resolver\MiddlewareResolverInterface>>
     */
    public const string RESOLVERS = 'Componenta\Http\Middleware::RESOLVERS';
}
