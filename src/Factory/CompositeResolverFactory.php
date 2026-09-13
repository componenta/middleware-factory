<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\Http\Middleware\ConfigKey;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\ClassNameResolver;
use Componenta\Http\Middleware\Resolver\CompositeResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareGroupResolver;
use Componenta\Config\ContainerValue;

final readonly class CompositeResolverFactory
{
    public function __invoke(ContainerValue $container): CompositeResolver
    {
        $resolver = new CompositeResolver();

        $config = $container->config;

        $resolvers = [
            ...$config->get(ConfigKey::RESOLVERS, []),
            MiddlewareGroupResolver::class,
            ClassNameResolver::class,
            CallableResolver::class,
        ];

        foreach ($resolvers as $entryId) {
            $resolver->add($container->get($entryId));
        }

        return $resolver;
    }
}
