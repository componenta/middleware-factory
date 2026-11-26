<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\Http\Middleware\Resolver\ServiceResolver;
use Psr\Container\ContainerInterface;

final readonly class ServiceResolverFactory
{
    public function __invoke(ContainerInterface $container): ServiceResolver
    {
        return new ServiceResolver($container);
    }
}
