<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\PipelineFactoryInterface;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Componenta\Http\Middleware\Resolver\ServiceResolver;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

final readonly class MiddlewareFactoryFactory
{
    public function __invoke(ContainerInterface $container): MiddlewareFactory
    {
        $resolver = $container->get(MiddlewareResolverInterface::class);
        $services = $container->get(ServiceResolver::class);
        $callables = $container->get(CallableResolver::class);
        $pipelines = $container->get(PipelineFactoryInterface::class);

        if (!$resolver instanceof MiddlewareResolverInterface
            || !$services instanceof ServiceResolver
            || !$callables instanceof CallableResolver
            || !$pipelines instanceof PipelineFactoryInterface
        ) {
            throw new UnexpectedValueException('Invalid middleware factory dependencies.');
        }

        return new MiddlewareFactory($resolver, $services, $callables, $pipelines);
    }
}
