<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\Config\Config;
use Componenta\Http\Middleware\ConfigKey;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\Resolver\ClassNameFirstResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Psr\Container\ContainerInterface;

final readonly class MiddlewareFactoryFactory
{
    public function __invoke(ContainerInterface $container): MiddlewareFactory
    {
        $config = $container->get(ConfigKey::CONFIG);
        if (!$config instanceof Config) {
            throw new \UnexpectedValueException(sprintf(
                'Container entry "%s" must be a %s; got %s.',
                ConfigKey::CONFIG,
                Config::class,
                get_debug_type($config),
            ));
        }

        if ($config->get(ConfigKey::RESOLVERS, []) !== []) {
            return new MiddlewareFactory($container->get(MiddlewareResolverInterface::class));
        }

        return new MiddlewareFactory(
            new ClassNameFirstResolver(
                $container,
                static fn(): MiddlewareResolverInterface => $container->get(MiddlewareResolverInterface::class),
            ),
        );
    }
}
