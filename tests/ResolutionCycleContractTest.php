<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\Cycles;

use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareGroup;
use Componenta\Http\Middleware\Resolver\CompositeResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Componenta\Http\Middleware\Resolver\ServiceResolver;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Throwable;

function terminal(): RequestHandlerInterface
{
    return new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(204);
        }
    };
}

function causeMessages(Throwable $failure): array
{
    $messages = [];
    do {
        $messages[] = $failure->getMessage();
        $failure = $failure->getPrevious();
    } while ($failure !== null);

    return $messages;
}

it('rejects an indirect identifier cycle through a reentrant extension before resolving an identifier again', function (): void {
    $extension = new class implements MiddlewareResolverInterface {
        public MiddlewareFactory $factory;
        /** @var list<string> */
        public array $calls = [];

        public function resolve(mixed $middleware): ?MiddlewareInterface
        {
            if ($middleware !== 'first' && $middleware !== 'second') {
                return null;
            }
            $this->calls[] = $middleware;
            if (count($this->calls) > 2) {
                throw new LogicException('The test recursion limit was reached.');
            }

            return $this->factory->createMiddleware($middleware === 'first' ? 'second' : 'first');
        }
    };
    $factory = new MiddlewareFactory(new CompositeResolver($extension));
    $extension->factory = $factory;

    $caught = null;
    try {
        $factory->createMiddleware('first');
    } catch (MiddlewareResolutionExceptionInterface $failure) {
        $caught = $failure;
    }

    expect($caught)->toBeInstanceOf(MiddlewareResolutionExceptionInterface::class)
        ->and($extension->calls)->toBe(['first', 'second'])
        ->and(implode(' ', causeMessages($caught)))->toContain('Cyclic middleware');
    $ready = new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            return new Response(201);
        }
    };
    expect($factory->createMiddleware($ready))->toBe($ready);
});

it('rejects a group cycle crossing a public factory call from a service provider', function (): void {
    $group = new MiddlewareGroup('reentrant');
    $services = new class($group) implements ContainerInterface {
        public MiddlewareFactory $factory;
        public int $calls = 0;

        public function __construct(private MiddlewareGroup $group) {}
        public function has(string $id): bool { return $id === 'reentrant'; }
        public function get(string $id): mixed
        {
            if (++$this->calls > 1) {
                throw new LogicException('The test recursion limit was reached.');
            }
            return $this->factory->createMiddleware($this->group);
        }
    };
    $factory = new MiddlewareFactory(new CompositeResolver(), new ServiceResolver($services));
    $services->factory = $factory;

    $caught = null;
    try {
        $factory->createMiddleware($group);
    } catch (MiddlewareResolutionExceptionInterface $failure) {
        $caught = $failure;
    }

    expect($caught)->toBeInstanceOf(MiddlewareResolutionExceptionInterface::class)
        ->and($services->calls)->toBe(1)
        ->and(implode(' ', causeMessages($caught)))->toContain('Cyclic middleware');
});

it('clears an identifier guard after a failed resolution and permits subsequent independent calls', function (): void {
    $extension = new class implements MiddlewareResolverInterface {
        public bool $fail = true;

        public function resolve(mixed $middleware): ?MiddlewareInterface
        {
            if ($middleware !== 'retry') {
                return null;
            }
            if ($this->fail) {
                throw new LogicException('initial failure');
            }

            return new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return new Response(202);
                }
            };
        }
    };
    $factory = new MiddlewareFactory(new CompositeResolver($extension));
    try {
        $factory->createMiddleware('retry');
    } catch (MiddlewareResolutionExceptionInterface $failure) {
        expect($failure->getPrevious())->toBeInstanceOf(LogicException::class);
    }

    $extension->fail = false;
    foreach ([1, 2] as $_) {
        expect($factory->createMiddleware('retry')->process(new ServerRequest('GET', '/'), terminal())->getStatusCode())->toBe(202);
    }
});


it('does not copy an active resolution guard into a cloned factory', function (): void {
    $extension = new class implements MiddlewareResolverInterface {
        public MiddlewareFactory $factory;
        private bool $nested = false;

        public function resolve(mixed $middleware): ?MiddlewareInterface
        {
            if (!$this->nested) {
                $this->nested = true;
                try {
                    return (clone $this->factory)->createMiddleware($middleware);
                } finally {
                    $this->nested = false;
                }
            }

            return new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return new Response(201);
                }
            };
        }
    };
    $factory = new MiddlewareFactory(new CompositeResolver($extension));
    $extension->factory = $factory;

    expect($factory->createMiddleware('shared')->process(new ServerRequest('GET', '/'), terminal())->getStatusCode())->toBe(201);
});
