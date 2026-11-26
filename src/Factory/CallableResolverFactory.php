<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\DI\CallableExecutorInterface;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Psr\Container\ContainerInterface;

final readonly class CallableResolverFactory
{
    public function __invoke(ContainerInterface $container): CallableResolver
    {
        return new CallableResolver($container->get(CallableExecutorInterface::class));
    }
}
