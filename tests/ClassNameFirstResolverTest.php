<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests;

use Componenta\Config\Config;
use Componenta\Http\Middleware\ConfigKey;
use Componenta\Http\Middleware\Factory\MiddlewareFactoryFactory;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareFactoryAwareInterface;
use Componenta\Http\Middleware\Resolver\ClassNameFirstResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ClassNameFirstResolverTest extends TestCase
{
    public function testClassNameDoesNotMaterializeFallbackResolver(): void
    {
        $middleware = new FastPathMiddleware();
        $container = new ResolverContainer([FastPathMiddleware::class => $middleware]);
        $fallbackCalls = 0;

        $factory = new MiddlewareFactory(new ClassNameFirstResolver(
            $container,
            static function () use (&$fallbackCalls): MiddlewareResolverInterface {
                ++$fallbackCalls;
                return new FallbackResolver(new FastPathMiddleware());
            },
        ));

        self::assertSame($middleware, $factory->createMiddleware(FastPathMiddleware::class));
        self::assertSame(0, $fallbackCalls);
    }

    public function testFallbackIsLazyAndReceivesMiddlewareFactory(): void
    {
        $fallbackMiddleware = new FastPathMiddleware();
        $fallback = new AwareFallbackResolver($fallbackMiddleware);
        $fallbackCalls = 0;

        $factory = new MiddlewareFactory(new ClassNameFirstResolver(
            new ResolverContainer([]),
            static function () use (&$fallbackCalls, $fallback): MiddlewareResolverInterface {
                ++$fallbackCalls;
                return $fallback;
            },
        ));

        self::assertSame($fallbackMiddleware, $factory->createMiddleware('custom-definition'));
        self::assertSame(1, $fallbackCalls);
        self::assertSame($factory, $fallback->factory);
    }

    public function testFactoryKeepsConfiguredCustomResolverPrecedence(): void
    {
        $middleware = new FastPathMiddleware();
        $customResolver = new FallbackResolver($middleware);
        $container = new ResolverContainer([
            ConfigKey::CONFIG => new Config([ConfigKey::RESOLVERS => ['custom-resolver']]),
            MiddlewareResolverInterface::class => $customResolver,
        ]);

        $factory = (new MiddlewareFactoryFactory())($container);

        self::assertSame($middleware, $factory->createMiddleware('custom-definition'));
    }
}

final class ResolverContainer implements ContainerInterface
{
    public function __construct(private readonly array $entries) {}

    public function get(string $id): mixed
    {
        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}

final class FastPathMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new Response();
    }
}

class FallbackResolver implements MiddlewareResolverInterface
{
    public function __construct(private readonly MiddlewareInterface $middleware) {}

    public function resolve(mixed $middleware): ?MiddlewareInterface
    {
        return $this->middleware;
    }
}

final class AwareFallbackResolver extends FallbackResolver implements MiddlewareFactoryAwareInterface
{
    public ?MiddlewareFactory $factory = null;

    public function setMiddlewareFactory(MiddlewareFactory $factory): void
    {
        $this->factory = $factory;
    }
}
