<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use Componenta\Http\Middleware\Factory\CallableResolverFactory;
use Componenta\Http\Middleware\Factory\ClassNameResolverFactory;
use Componenta\Http\Middleware\Factory\CompositeResolverFactory;
use Componenta\Http\Middleware\Factory\LegacyCallableResolverFactory;
use Componenta\Http\Middleware\Factory\MiddlewareFactoryFactory;
use Componenta\Http\Middleware\Factory\MiddlewareGroupResolverFactory;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\ClassNameResolver;
use Componenta\Http\Middleware\Resolver\LegacyCallableResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareGroupResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;

class ConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getProviders(): array
    {
        return [new PipelineConfigProvider];
    }

    protected function getFactories(): array
    {
        return [
            MiddlewareFactory::class => MiddlewareFactoryFactory::class,
            MiddlewareResolverInterface::class => CompositeResolverFactory::class,
            MiddlewareGroupResolver::class => MiddlewareGroupResolverFactory::class,
            ClassNameResolver::class => ClassNameResolverFactory::class,
            CallableResolver::class => CallableResolverFactory::class,
            LegacyCallableResolver::class => LegacyCallableResolverFactory::class,
        ];
    }
}
