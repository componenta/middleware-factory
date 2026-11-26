<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\Invocation;

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\Attribute\CurrentRequest;
use Componenta\DI\ContainerFactory;
use Componenta\DI\Exception\ResolutionException;
use Componenta\Http\Middleware\ConfigProvider;
use Componenta\Http\Middleware\MiddlewareFactory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

it('does not implicitly supply positional continuations or preconstructed responses', function (bool $double): void {
    $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider());
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = $container->get(MiddlewareFactory::class);
    $calls = 0;
    $callback = $double
        ? static function (#[CurrentRequest] ServerRequestInterface $request, ResponseInterface $response, callable $next) use (&$calls): ResponseInterface {
            ++$calls;
            return $next($request);
        }
        : static function (#[CurrentRequest] ServerRequestInterface $request, callable $next) use (&$calls): ResponseInterface {
            ++$calls;
            return $next($request);
        };

    $middleware = $factory->createMiddleware($callback);
    $terminal = new class implements RequestHandlerInterface {
        public int $calls = 0;
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            ++$this->calls;
            return new Response(204);
        }
    };

    expect(fn () => $middleware->process(new ServerRequest('GET', '/'), $terminal))
        ->toThrow(ResolutionException::class)
        ->and($calls)->toBe(0)
        ->and($terminal->calls)->toBe(0);
})->with(['callable continuation' => [false], 'response and callable continuation' => [true]]);
