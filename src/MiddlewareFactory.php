<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use Componenta\Http\Middleware\Adapter\RequestHandlerAdapter;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;
use Componenta\Http\Middleware\Internal\ResolutionGuard;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Componenta\Http\Middleware\Resolver\ServiceResolver;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Resolves definitions into PSR-15 middleware.
 * The transient resolution guard is isolated per execution context.
 */
final readonly class MiddlewareFactory
{
    private ResolutionGuard $guard;

    public function __construct(
        private MiddlewareResolverInterface $resolver,
        private ?ServiceResolver $services = null,
        private ?CallableResolver $callables = null,
        private PipelineFactoryInterface $pipelines = new PipelineFactory(),
    ) {
        $this->guard = new ResolutionGuard();
    }

    public function __clone(): void
    {
        // A derived factory shares its dependencies, not an in-flight stack.
        $this->guard = new ResolutionGuard();
    }

    /** @throws MiddlewareResolutionExceptionInterface */
    public function createMiddleware(mixed $middleware): MiddlewareInterface
    {
        // Extension resolvers may run user code or suspend. Capture array
        // slots before handing the definition to the first resolver.
        if (is_array($middleware)) {
            $snapshot = [];
            foreach ($middleware as $key => $value) {
                $snapshot[$key] = $value;
            }
            $middleware = $snapshot;
        }

        return $this->resolve($middleware);
    }

    private function resolve(mixed $definition): MiddlewareInterface
    {
        if ($definition instanceof MiddlewareInterface) {
            return $definition;
        }

        if ($definition instanceof RequestHandlerInterface) {
            return new RequestHandlerAdapter($definition);
        }

        $key = ResolutionGuard::keyFor($definition);
        $context = $key === null ? null : $this->guard->enter($key, $definition);

        try {
            if ($definition instanceof MiddlewareGroup) {
                $resolved = [];
                foreach ($definition as $index => $member) {
                    try {
                        $resolved[] = $this->resolve($member);
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
                $resolved = $this->services->resolve(
                    $definition,
                    function (mixed $entry): MiddlewareInterface {
                        if ($entry instanceof MiddlewareInterface
                            || $entry instanceof RequestHandlerInterface
                            || $entry instanceof MiddlewareGroup
                        ) {
                            return $this->resolve($entry);
                        }
                        if ($this->callables !== null && (is_array($entry) || is_callable($entry))) {
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
        } finally {
            if ($key !== null && $context !== null) {
                $this->guard->leave($context, $key);
            }
        }
    }
}
