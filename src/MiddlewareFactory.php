<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use Componenta\Http\Middleware\Adapter\RequestHandlerAdapter;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Componenta\Http\Middleware\Resolver\ServiceResolver;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Resolves definitions into PSR-15 middleware. All recursion state belongs to
 * the current resolution, never to the factory or its shared resolvers.
 */
final readonly class MiddlewareFactory
{
    public function __construct(
        private MiddlewareResolverInterface $resolver,
        private ?ServiceResolver $services = null,
        private ?CallableResolver $callables = null,
        private PipelineFactoryInterface $pipelines = new PipelineFactory(),
    ) {}

    /** @throws MiddlewareResolutionExceptionInterface */
    public function createMiddleware(mixed $middleware): MiddlewareInterface
    {
        return $this->resolve($middleware, [], []);
    }

    /**
     * @param array<string, true> $activeServices
     * @param array<int, true> $activeGroups
     */
    private function resolve(mixed $definition, array $activeServices, array $activeGroups): MiddlewareInterface
    {
        try {
            if ($definition instanceof MiddlewareInterface) {
                return $definition;
            }

            if ($definition instanceof RequestHandlerInterface) {
                return new RequestHandlerAdapter($definition);
            }

            if ($definition instanceof MiddlewareGroup) {
                $id = spl_object_id($definition);
                if (isset($activeGroups[$id])) {
                    throw new MiddlewareResolutionException($definition, 'Cyclic middleware group.');
                }
                $activeGroups[$id] = true;

                $resolved = [];
                foreach ($definition as $index => $member) {
                    try {
                        $resolved[] = $this->resolve($member, $activeServices, $activeGroups);
                    } catch (MiddlewareResolutionExceptionInterface $e) {
                        throw new MiddlewareResolutionException(
                            $definition,
                            sprintf('Failed to resolve middleware at index %d in group.', $index),
                            $e,
                        );
                    }
                }

                return $this->pipelines->createMiddlewarePipeline($resolved);
            }

            $resolved = $this->resolver->resolve($definition);
            if ($resolved !== null) {
                return $resolved;
            }

            if (is_string($definition) && $this->services !== null) {
                $key = 'service:' . $definition;
                if (isset($activeServices[$key])) {
                    throw new MiddlewareResolutionException($definition, 'Cyclic middleware service definition.');
                }
                $activeServices[$key] = true;

                $resolved = $this->services->resolve(
                    $definition,
                    function (mixed $entry) use ($activeServices, $activeGroups): MiddlewareInterface {
                        if ($entry instanceof MiddlewareInterface
                            || $entry instanceof RequestHandlerInterface
                            || $entry instanceof MiddlewareGroup
                        ) {
                            return $this->resolve($entry, $activeServices, $activeGroups);
                        }
                        if ($this->callables !== null && is_callable($entry)) {
                            return $this->callables->adapt($entry);
                        }

                        throw new MiddlewareResolutionException(
                            $entry,
                            'Middleware service must return middleware, a request handler, a group, or a callable.',
                        );
                    },
                );
                if ($resolved !== null) {
                    return $resolved;
                }
            }

            $resolved = $this->callables?->resolve($definition);
            if ($resolved !== null) {
                return $resolved;
            }

            throw new MiddlewareResolutionException($definition);
        } catch (MiddlewareResolutionExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            throw MiddlewareResolutionException::fromPrevious($definition, $e);
        }
    }
}
