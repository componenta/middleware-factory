<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\Legacy;

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\CallableInvoker;
use Componenta\DI\CallableExecutorInterface;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\Adapter\CallableAdapter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

it('preserves the legacy positional convention with native and DI executors', function (bool $double, bool $native): void {
    $composition = (new ConfigFactory())->create(new Environment([]));
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $executor = $native ? new CallableInvoker() : $container->get(CallableExecutorInterface::class);
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(200, [], $request->getUri()->getPath());
        }
    };
    $middleware = $double
        ? CallableAdapter::doublePassMiddleware(
            static function (ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface {
                return $next($request)->withHeader('X-Output', $request->getHeaderLine('X-Input'))->withHeader('X-Initial', (string) $response->getStatusCode());
            },
            $executor,
            new Psr17Factory(),
        )
        : CallableAdapter::singlePassMiddleware(
            static function (ServerRequestInterface $request, callable $next): ResponseInterface {
                return $next($request)->withHeader('X-Output', $request->getHeaderLine('X-Input'));
            },
            $executor,
        );

    foreach (['first', 'second'] as $input) {
        $response = $middleware->process(new ServerRequest('GET', '/' . $input, ['X-Input' => $input]), $terminal);
        expect((string) $response->getBody())->toBe('/' . $input)
            ->and($response->getHeaderLine('X-Output'))->toBe($input)
            ->and($response->getHeaderLine('X-Initial'))->toBe($double ? '200' : '');
    }
})->with(['single pass' => [false], 'double pass' => [true]])
    ->with(['DI executor' => [false], 'native invoker' => [true]]);
