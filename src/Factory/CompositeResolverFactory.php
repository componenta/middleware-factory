<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\Http\Middleware\ConfigKey;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\ClassNameResolver;
use Componenta\Http\Middleware\Resolver\CompositeResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareGroupResolver;
use Psr\Container\ContainerInterface;

final readonly class CompositeResolverFactory
{
    public function __invoke(ContainerInterface $container): CompositeResolver
    {
        $resolver = new CompositeResolver();

        $config = $container->get(ConfigKey::CONFIG);

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
