<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\Http\Middleware\Resolver\ClassNameResolver;
use Psr\Container\ContainerInterface;

final readonly class ClassNameResolverFactory
{
    public function __invoke(ContainerInterface $container): ClassNameResolver
    {
        return new ClassNameResolver($container);
    }
}
