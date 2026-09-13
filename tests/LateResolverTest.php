<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests;

use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareGroup;
use Componenta\Http\Middleware\Resolver\CompositeResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareGroupResolver;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LateResolverTest extends TestCase
{
    public static function positions(): iterable
    {
        yield 'append' => [false];
        yield 'prepend' => [true];
    }

    #[DataProvider('positions')]
    public function testResolvesGroupsRegisteredAfterFactoryConstruction(bool $prepend): void
    {
        $resolvers = new CompositeResolver();
        $factory = new MiddlewareFactory($resolvers);
        $nested = new CompositeResolver();
        $resolvers->add($nested, $prepend);
        $nested->add(new MiddlewareGroupResolver(), $prepend);
        $response = new Response(202, [], 'resolved');
        $terminal = new class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $middleware = $factory->createMiddleware(new MiddlewareGroup(new MiddlewareGroup($terminal)));

        self::assertSame($response, $middleware->process(new ServerRequest('GET', '/'), $terminal));
    }
}
