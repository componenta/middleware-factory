<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests;

use Componenta\Config\ConfigFactory;
use Componenta\Config\ConfigKey;
use Componenta\Config\Environment;
use Componenta\DI\Attribute\CurrentRequest;
use Componenta\DI\Attribute\Header;
use Componenta\DI\ContainerFactory;
use Componenta\DI\CallableExecutorInterface;
use Componenta\Http\Middleware\ConfigProvider;
use Componenta\Http\Middleware\ConfigKey as MiddlewareConfigKey;
use Componenta\Http\Middleware\PipelineConfigProvider;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Psr\Container\ContainerInterface;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareGroup;
use Componenta\Http\Middleware\PipelineFactoryInterface;
use Componenta\Http\Middleware\PipelineInterface;
use Componenta\Http\Middleware\Pipeline;
use Componenta\Http\Middleware\EmptyPipelineHandler;
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


it('allows applications to replace the callable resolver factory with an explicit response normalizer', function (): void {
    $composition = (new ConfigFactory())->create(
        new Environment([]),
        new ConfigProvider(),
        static fn (): array => [
            ConfigKey::DEPENDENCIES => [
                ConfigKey::FACTORIES => [
                    CallableResolver::class => static fn (ContainerInterface $container): CallableResolver => new CallableResolver(
                        $container->get(CallableExecutorInterface::class),
                        static fn (mixed $result): ResponseInterface => new Response(201, [], json_encode($result, JSON_THROW_ON_ERROR)),
                    ),
                ],
            ],
        ],
    );
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = $container->get(MiddlewareFactory::class);
    $middleware = $factory->createMiddleware(static fn (#[CurrentRequest] ServerRequestInterface $request): array => [
        'path' => $request->getUri()->getPath(),
    ]);
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            throw new \LogicException('The callback should produce a response without delegation.');
        }
    };

    foreach (['/first', '/second'] as $path) {
        $response = $middleware->process(new ServerRequest('GET', $path), $terminal);
        expect($response->getStatusCode())->toBe(201)
            ->and(json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR))->toBe(['path' => $path]);
    }
});


it('uses an explicitly configured pipeline factory for group resolution', function (): void {
    $custom = new class implements PipelineFactoryInterface {
        public int $calls = 0;

        public function createMiddlewarePipeline(
            iterable $middlewares = [],
            ?RequestHandlerInterface $fallbackHandler = null,
        ): PipelineInterface {
            ++$this->calls;
            return new Pipeline($middlewares, $fallbackHandler ?? new EmptyPipelineHandler());
        }
    };
    $composition = (new ConfigFactory())->create(
        new Environment([]),
        new ConfigProvider(),
        static fn (): array => [
            ConfigKey::DEPENDENCIES => [
                ConfigKey::ALIASES => [PipelineFactoryInterface::class => 'application.pipeline.factory'],
                ConfigKey::SERVICES => ['application.pipeline.factory' => $custom],
            ],
        ],
    );
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = $container->get(MiddlewareFactory::class);
    $group = $factory->createMiddleware(new MiddlewareGroup(
        static fn (): ResponseInterface => new Response(201),
    ));
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            throw new \LogicException('The group should produce a response without delegation.');
        }
    };

    expect($container->get(PipelineFactoryInterface::class))->toBe($custom)
        ->and($custom->calls)->toBe(1)
        ->and($group->process(new ServerRequest('GET', '/custom'), $terminal)->getStatusCode())->toBe(201);
});
