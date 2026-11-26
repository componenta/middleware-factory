<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Factory;

use Componenta\DI\CallableExecutorInterface;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

final readonly class CallableResolverFactory
{
    public function __invoke(ContainerInterface $container): CallableResolver
    {
        $executor = $container->get(CallableExecutorInterface::class);
        if (!$executor instanceof CallableExecutorInterface) {
            throw new UnexpectedValueException('Invalid callable executor service.');
        }

        return new CallableResolver($executor);
    }
}
