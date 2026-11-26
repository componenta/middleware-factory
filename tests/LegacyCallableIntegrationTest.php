<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\Legacy;

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\Attribute\CurrentRequest;
use Componenta\DI\Attribute\Header;
use Componenta\DI\CallableExecutorInterface;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\Adapter\CallableAdapter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

it('provides the HTTP context consistently to both explicitly selected legacy adapters', function (bool $double): void {
    $composition = (new ConfigFactory())->create(new Environment([]));
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $executor = $container->get(CallableExecutorInterface::class);
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(200, [], $request->getUri()->getPath());
        }
    };
    $middleware = $double
        ? CallableAdapter::doublePassMiddleware(
            static function (#[CurrentRequest] ServerRequestInterface $request, ResponseInterface $response, callable $next, #[Header('X-Input')] string $input): ResponseInterface {
                return $next($request)->withHeader('X-Output', $input);
            },
            $executor,
            new Psr17Factory(),
        )
        : CallableAdapter::singlePassMiddleware(
            static function (#[CurrentRequest] ServerRequestInterface $request, callable $next, #[Header('X-Input')] string $input): ResponseInterface {
                return $next($request)->withHeader('X-Output', $input);
            },
            $executor,
        );

    foreach (['first', 'second'] as $input) {
        $response = $middleware->process(new ServerRequest('GET', '/' . $input, ['X-Input' => $input]), $terminal);
        expect((string) $response->getBody())->toBe('/' . $input)
            ->and($response->getHeaderLine('X-Output'))->toBe($input);
    }
})->with(['single pass' => [false], 'double pass' => [true]]);
