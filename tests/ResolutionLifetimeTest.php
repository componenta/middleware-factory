<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\Lifetime;

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\Attribute\CurrentRequest;
use Componenta\DI\CallableExecutorInterface;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\ConfigProvider;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareGroup;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\CompositeResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Componenta\Http\Middleware\Resolver\ServiceResolver;
use DomainException;
use Fiber;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WeakReference;

it('isolates concurrent group resolutions with the same service identifier', function (): void {
    $services = new class implements ContainerInterface {
        public function has(string $id): bool { return $id === 'group'; }

        public function get(string $id): mixed
        {
            $status = Fiber::suspend($id);
            return new MiddlewareGroup(static fn (): ResponseInterface => new Response($status));
        }
    };
    $composition = (new ConfigFactory())->create(new Environment([]));
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = new MiddlewareFactory(new CompositeResolver(), new ServiceResolver($services), new CallableResolver($container->get(CallableExecutorInterface::class)));

    $first = new Fiber(fn () => $factory->createMiddleware('group'));
    $second = new Fiber(fn () => $factory->createMiddleware('group'));
    expect($first->start())->toBe('group')->and($second->start())->toBe('group');
    $second->resume(202);
    $first->resume(201);

    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            throw new \LogicException('The group must return its own response.');
        }
    };
    $request = new ServerRequest('GET', '/');
    expect($first->getReturn()->process($request, $terminal)->getStatusCode())->toBe(201)
        ->and($second->getReturn()->process($request, $terminal)->getStatusCode())->toBe(202);
});

it('allows an extension to resolve a different definition through the same factory', function (): void {
    $extension = new class implements MiddlewareResolverInterface {
        public MiddlewareFactory $factory;

        public function resolve(mixed $middleware): ?MiddlewareInterface
        {
            return $middleware === 'extension'
                ? $this->factory->createMiddleware(new MiddlewareGroup(
                    static fn (): ResponseInterface => new Response(201),
                ))
                : null;
        }
    };
    $composition = (new ConfigFactory())->create(new Environment([]));
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = new MiddlewareFactory(new CompositeResolver($extension), null, new CallableResolver($container->get(CallableExecutorInterface::class)));
    $extension->factory = $factory;
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            throw new \LogicException('The extension must return its own response.');
        }
    };

    expect($factory->createMiddleware('extension')->process(new ServerRequest('GET', '/'), $terminal)->getStatusCode())->toBe(201);
});

it('releases completed request and continuation objects through the real DI executor', function (bool $fail): void {
    $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider());
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = $container->get(MiddlewareFactory::class);
    $middleware = $factory->createMiddleware(
        static fn (#[CurrentRequest] ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface => $next->handle($request),
    );

    $request = new ServerRequest('GET', '/first');
    $terminal = new class($fail) implements RequestHandlerInterface {
        public function __construct(private bool $fail) {}

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            if ($this->fail) {
                throw new DomainException('request failed');
            }
            return new Response(201, [], $request->getUri()->getPath());
        }
    };
    $requestRef = WeakReference::create($request);
    $terminalRef = WeakReference::create($terminal);
    $caught = false;

    try {
        $response = $middleware->process($request, $terminal);
        expect((string) $response->getBody())->toBe('/first');
        unset($response);
    } catch (DomainException) {
        $caught = true;
    }

    expect($caught)->toBe($fail);
    unset($request, $terminal);
    gc_collect_cycles();

    expect($requestRef->get())->toBeNull()
        ->and($terminalRef->get())->toBeNull();

    $next = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(202, [], $request->getUri()->getPath());
        }
    };
    expect((string) $middleware->process(new ServerRequest('GET', '/after'), $next)->getBody())->toBe('/after');
})->with(['success' => [false], 'failure' => [true]]);
