<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\Http\Middleware\Resolver\MiddlewareGroupResolver;
use Psr\Container\ContainerInterface;

final readonly class MiddlewareGroupResolverFactory
{
    public function __invoke(ContainerInterface $container): MiddlewareGroupResolver
    {
        return new MiddlewareGroupResolver();
    }
}
