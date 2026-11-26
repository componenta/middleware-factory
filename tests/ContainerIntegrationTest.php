<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests;

use Componenta\Config\ConfigFactory;
use Componenta\Config\ConfigKey;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\ConfigProvider;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareGroup;
use Componenta\Http\Middleware\PipelineFactoryInterface;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

it('configures the factory, opaque service identifiers, callable injection and pipeline factory', function () {
    $middleware = new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            return $handler->handle($request)->withHeader('X-Test', 'yes');
        }
    };
    $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider(),
        static fn (): array => [ConfigKey::DEPENDENCIES => [ConfigKey::SERVICES => [
            'middleware.test' => $middleware,
            'group.test' => new MiddlewareGroup('middleware.test', static fn (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface => $handler->handle($request)),
        ]]]);
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = $container->get(MiddlewareFactory::class);
    $pipelineFactory = $container->get(PipelineFactoryInterface::class);
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(202, [], $request->getUri()->getPath());
        }
    };

    expect($pipelineFactory)->toBeInstanceOf(PipelineFactoryInterface::class)
        ->and($factory->createMiddleware('middleware.test'))->toBe($middleware);
    $response = $factory->createMiddleware('group.test')->process(new ServerRequest('GET', '/configured'), $terminal);
    expect($response->getStatusCode())->toBe(202)
        ->and($response->getHeaderLine('X-Test'))->toBe('yes')
        ->and((string) $response->getBody())->toBe('/configured');
});
