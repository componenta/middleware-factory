<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\DI\CallableExecutorInterface;
use Componenta\Http\Middleware\Resolver\LegacyCallableResolver;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final readonly class LegacyCallableResolverFactory
{
    public function __invoke(ContainerInterface $container): LegacyCallableResolver
    {
        return new LegacyCallableResolver(
            $container->get(CallableExecutorInterface::class),
            $container->get(ResponseFactoryInterface::class),
        );
    }
}
