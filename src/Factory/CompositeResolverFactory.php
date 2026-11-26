<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\Config\ContainerValue;
use Componenta\Http\Middleware\ConfigKey;
use Componenta\Http\Middleware\Resolver\CompositeResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use InvalidArgumentException;

final readonly class CompositeResolverFactory
{
    public function __invoke(ContainerValue $container): CompositeResolver
    {
        $ids = $container->config->get(ConfigKey::RESOLVERS, []);
        if (!is_array($ids) || !array_is_list($ids)) {
            throw new InvalidArgumentException('Middleware resolvers must be a list of service identifiers.');
        }

        $resolvers = [];
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') {
                throw new InvalidArgumentException('Each middleware resolver identifier must be a non-empty string.');
            }

            $resolver = $container->get($id);
            if (!$resolver instanceof MiddlewareResolverInterface) {
                throw new InvalidArgumentException('Configured service does not implement MiddlewareResolverInterface.');
            }

            $resolvers[] = $resolver;
        }

        return new CompositeResolver(...$resolvers);
    }
}
