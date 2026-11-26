<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests;

use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\Resolver\CompositeResolver;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

it('returns a ready non-invokable middleware unchanged', function () {
    $middleware = new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            return $handler->handle($request);
        }
    };

    expect((new MiddlewareFactory(new CompositeResolver()))->createMiddleware($middleware))->toBe($middleware);
});

it('adapts a request handler into a terminal middleware', function () {
    $handler = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface { return new Response(201); }
    };
    $fallback = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface { throw new \LogicException('not reached'); }
    };
    $middleware = (new MiddlewareFactory(new CompositeResolver()))->createMiddleware($handler);
    expect($middleware->process(new ServerRequest('GET', '/'), $fallback)->getStatusCode())->toBe(201);
});

it('throws a typed failure for an unresolved definition', function () {
    $factory = new MiddlewareFactory(new CompositeResolver());
    expect(fn () => $factory->createMiddleware('missing.middleware'))->toThrow(MiddlewareResolutionExceptionInterface::class);
});
