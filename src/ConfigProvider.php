<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use Componenta\Http\Middleware\Factory\CallableResolverFactory;
use Componenta\Http\Middleware\Factory\CompositeResolverFactory;
use Componenta\Http\Middleware\Factory\MiddlewareFactoryFactory;
use Componenta\Http\Middleware\Factory\ServiceResolverFactory;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Componenta\Http\Middleware\Resolver\ServiceResolver;

final class ConfigProvider extends \Componenta\Config\ConfigProvider
{
    /** @return array<class-string, class-string> */
    protected function getInvokables(): array
    {
        return [PipelineFactoryInterface::class => PipelineFactory::class];
    }

    /** @return array<class-string, class-string> */
    protected function getFactories(): array
    {
        return [
            MiddlewareFactory::class => MiddlewareFactoryFactory::class,
            MiddlewareResolverInterface::class => CompositeResolverFactory::class,
            ServiceResolver::class => ServiceResolverFactory::class,
            CallableResolver::class => CallableResolverFactory::class,
        ];
    }
}
