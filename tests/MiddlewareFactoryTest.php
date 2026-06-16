<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests;

use Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareFactoryTest extends TestCase
{
    public function testReturnsExistingMiddlewareInstance(): void
    {
        $middleware = new RecordingMiddleware();
        $factory = new MiddlewareFactory(new NullResolver());

        self::assertSame($middleware, $factory->createMiddleware($middleware));
    }

    public function testAdaptsRequestHandlerToMiddleware(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(201);
            }
        };
        $factory = new MiddlewareFactory(new NullResolver());
        $middleware = $factory->createMiddleware($handler);

        $response = $middleware->process(new ServerRequest('GET', '/'), new FallbackHandler());

        self::assertSame(201, $response->getStatusCode());
    }

    public function testThrowsTypedExceptionWhenDefinitionCannotBeResolved(): void
    {
        $factory = new MiddlewareFactory(new NullResolver());

        $this->expectException(MiddlewareResolutionExceptionInterface::class);

        $factory->createMiddleware('missing.middleware');
    }
}

final class NullResolver implements MiddlewareResolverInterface
{
    public function resolve(mixed $middleware): ?MiddlewareInterface
    {
        return null;
    }
}

final class RecordingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

final class FallbackHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(204);
    }
}
