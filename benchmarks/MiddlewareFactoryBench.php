<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Benchmarks;

use Componenta\DI\CallableExecutorInterface;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareGroup;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\CompositeResolver;
use Componenta\Http\Middleware\Resolver\ServiceResolver;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PhpBench\Attributes as Bench;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Bench\BeforeMethods('setUp')]
#[Bench\Revs(1000)]
#[Bench\Iterations(5)]
final class MiddlewareFactoryBench
{
    private MiddlewareFactory $factory;
    private MiddlewareInterface $instance;
    private MiddlewareGroup $group;
    private \Closure $callable;
    private MiddlewareInterface $adapted;
    private ServerRequestInterface $request;
    private RequestHandlerInterface $terminal;

    public function setUp(): void
    {
        $this->instance = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $container = new class($this->instance) implements ContainerInterface {
            public function __construct(private MiddlewareInterface $middleware) {}
            public function has(string $id): bool { return $id === 'middleware'; }
            public function get(string $id): mixed { return $this->middleware; }
        };
        // Isolate middleware factory costs from DI's independent benchmarks.
        $executor = new class implements CallableExecutorInterface {
            public function resolve(mixed $callable): callable { return $callable; }
            public function call(mixed $callable, array $params = []): mixed { return $callable(...array_values($params)); }
        };
        $this->factory = new MiddlewareFactory(new CompositeResolver(), new ServiceResolver($container), new CallableResolver($executor));
        $this->group = MiddlewareGroup::fromArray(array_fill(0, 20, 'middleware'));
        $this->callable = static fn (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface => $handler->handle($request);
        $this->adapted = $this->factory->createMiddleware($this->callable);
        $this->request = new ServerRequest('GET', '/');
        $this->terminal = new class implements RequestHandlerInterface {
            private ResponseInterface $response;
            public function __construct() { $this->response = new Response(204); }
            public function handle(ServerRequestInterface $request): ResponseInterface { return $this->response; }
        };
    }

    public function benchExisting(): void { $this->factory->createMiddleware($this->instance); }
    public function benchService(): void { $this->factory->createMiddleware('middleware'); }
    public function benchCallable(): void { $this->factory->createMiddleware($this->callable); }
    public function benchGroup(): void { $this->factory->createMiddleware($this->group); }
    public function benchAdaptedInvocation(): void { $this->adapted->process($this->request, $this->terminal); }
}
