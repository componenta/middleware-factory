<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests;

use Componenta\Config\ConfigFactory;
use Componenta\Config\ConfigKey;
use Componenta\Config\Environment;
use Componenta\DI\Attribute\CurrentRequest;
use Componenta\DI\Attribute\Header;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\ConfigProvider;
use Componenta\Http\Middleware\ConfigKey as MiddlewareConfigKey;
use Componenta\Http\Middleware\PipelineConfigProvider;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
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
            'group.test' => new MiddlewareGroup('middleware.test', static fn (#[CurrentRequest] ServerRequestInterface $request, RequestHandlerInterface $handler, #[Header('X-Input')] string $input): ResponseInterface => $handler->handle($request)->withHeader('X-Resolved', $input)),
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
    $group = $factory->createMiddleware('group.test');
    foreach (['first', 'second'] as $input) {
        $response = $group->process(new ServerRequest('GET', '/' . $input, ['X-Input' => $input]), $terminal);
        expect($response->getStatusCode())->toBe(202)
            ->and($response->getHeaderLine('X-Test'))->toBe('yes')
            ->and($response->getHeaderLine('X-Resolved'))->toBe($input)
            ->and((string) $response->getBody())->toBe('/' . $input);
    }
});


it('composes both providers and resolves configured extension services before container entries', function () {
    $middleware = new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            return new Response(202);
        }
    };
    $extension = new class($middleware) implements MiddlewareResolverInterface {
        public function __construct(private MiddlewareInterface $middleware) {}
        public function resolve(mixed $middleware): ?MiddlewareInterface
        {
            return $middleware === 'custom' ? $this->middleware : null;
        }
    };
    $composition = (new ConfigFactory())->create(
        new Environment([]),
        new PipelineConfigProvider(),
        new ConfigProvider(),
        static fn (): array => [
            MiddlewareConfigKey::RESOLVERS => ['extension'],
            ConfigKey::DEPENDENCIES => [ConfigKey::SERVICES => [
                'extension' => $extension,
                'custom' => new \stdClass(),
            ]],
        ],
    );
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = $container->get(MiddlewareFactory::class);

    expect($factory->createMiddleware('custom'))->toBe($middleware)
        ->and($container->get(PipelineFactoryInterface::class))->toBeInstanceOf(PipelineFactoryInterface::class);
});
