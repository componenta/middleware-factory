<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\PipelineFactoryInterface;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Componenta\Http\Middleware\Resolver\ServiceResolver;
use Psr\Container\ContainerInterface;

final readonly class MiddlewareFactoryFactory
{
    public function __invoke(ContainerInterface $container): MiddlewareFactory
    {
        return new MiddlewareFactory(
            $container->get(MiddlewareResolverInterface::class),
            $container->get(ServiceResolver::class),
            $container->get(CallableResolver::class),
            $container->get(PipelineFactoryInterface::class),
        );
    }
}
