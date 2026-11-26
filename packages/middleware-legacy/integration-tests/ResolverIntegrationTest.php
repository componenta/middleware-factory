<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Legacy\Tests\Integration;

use Closure;
use Componenta\Config\ConfigFactory;
use Componenta\Config\ConfigKey;
use Componenta\Config\Environment;
use Componenta\DI\Attribute\CurrentRequest;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\ConfigKey as MiddlewareConfigKey;
use Componenta\Http\Middleware\ConfigProvider;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Componenta\Http\Middleware\Legacy\DoublePassAdapter;
use Componenta\Http\Middleware\Legacy\Resolver\DoublePassResolver;
use Componenta\Http\Middleware\Legacy\Resolver\SinglePassResolver;
use Componenta\Http\Middleware\Legacy\SinglePassAdapter;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareGroup;
use DomainException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

function single(ServerRequestInterface $request, callable $next): ResponseInterface
{
    return $next($request)->withAddedHeader('X-Layer', 'single');
}

function double(ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface
{
    return $next($request)->withAddedHeader('X-Layer', 'double');
}

final class Methods
{
    public static function single(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return single($request, $next);
    }

    public function double(ServerRequestInterface $request, ResponseInterface $response, Closure $next): ResponseInterface
    {
        return double($request, $response, $next);
    }

    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return single($request, $next);
    }
}

function terminal(): RequestHandlerInterface
{
    return new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(202, [], $request->getUri()->getPath());
        }
    };
}

function emptyContainer(): ContainerInterface
{
    return new class implements ContainerInterface {
        public function has(string $id): bool { return false; }
        public function get(string $id): mixed { throw new \LogicException('The resolver must not look up service values.'); }
    };
}

function factory(bool $legacy, array $services = []): MiddlewareFactory
{
    $composition = (new ConfigFactory())->create(
        new Environment([]),
        new ConfigProvider(),
        static fn (): array => [
            MiddlewareConfigKey::RESOLVERS => $legacy ? [SinglePassResolver::class, DoublePassResolver::class] : [],
            ConfigKey::DEPENDENCIES => [
                ConfigKey::SERVICES => [ResponseFactoryInterface::class => new Psr17Factory(), ...$services],
                ConfigKey::FACTORIES => [
                    SinglePassResolver::class => static fn (ContainerInterface $container): SinglePassResolver => new SinglePassResolver($container),
                    DoublePassResolver::class => static fn (ContainerInterface $container): DoublePassResolver => new DoublePassResolver(
                        $container,
                        $container->get(ResponseFactoryInterface::class),
                    ),
                ],
            ],
        ],
    );

    return (new ContainerFactory())->create($composition->config, $composition->dependencies)->container->get(MiddlewareFactory::class);
}

it('resolves native legacy definitions through the corresponding optional resolver', function (string $form, bool $double): void {
    $target = new Methods();
    $definition = match ([$form, $double]) {
        ['closure', false] => single(...),
        ['closure', true] => double(...),
        ['function', false] => __NAMESPACE__ . '\\single',
        ['function', true] => __NAMESPACE__ . '\\double',
        ['pair', false] => [Methods::class, 'single'],
        ['pair', true] => [$target, 'double'],
    };
    $single = new SinglePassResolver(emptyContainer());
    $doubleResolver = new DoublePassResolver(emptyContainer(), new Psr17Factory());

    expect(($double ? $single : $doubleResolver)->resolve($definition))->toBeNull();
    $adapter = ($double ? $doubleResolver : $single)->resolve($definition);
    expect($adapter)->toBeInstanceOf($double ? DoublePassAdapter::class : SinglePassAdapter::class)
        ->and($adapter->process(new ServerRequest('GET', '/native'), terminal())->getHeader('X-Layer'))->toBe([$double ? 'double' : 'single']);
})->with(['closure', 'function', 'pair'])->with(['single-pass' => [false], 'double-pass' => [true]]);

it('does not intercept present service identifiers or access their values', function (): void {
    $id = __NAMESPACE__ . '\\single';
    $container = new class($id) implements ContainerInterface {
        public int $gets = 0;
        public function __construct(private string $id) {}
        public function has(string $id): bool { return $id === $this->id; }
        public function get(string $id): mixed { ++$this->gets; throw new \LogicException('not reached'); }
    };
    expect((new SinglePassResolver($container))->resolve($id))->toBeNull()
        ->and((new DoublePassResolver($container, new Psr17Factory()))->resolve($id))->toBeNull()
        ->and($container->gets)->toBe(0);
});

it('preserves container lookup failures rather than falling back to another interpretation', function (bool $double): void {
    $failure = new DomainException('original failure');
    $container = new class($failure) implements ContainerInterface {
        public function __construct(private DomainException $failure) {}
        public function has(string $id): bool { throw $this->failure; }
        public function get(string $id): mixed { throw new \LogicException('not reached'); }
    };
    $resolver = $double ? new DoublePassResolver($container, new Psr17Factory()) : new SinglePassResolver($container);
    $caught = null;
    try {
        $resolver->resolve('service');
    } catch (MiddlewareResolutionException $error) {
        $caught = $error;
    }
    expect($caught)->toBeInstanceOf(MiddlewareResolutionException::class)
        ->and($caught->getPrevious())->toBe($failure);
})->with(['single-pass' => [false], 'double-pass' => [true]]);

it('integrates opt-in legacy resolvers alongside normal DI middleware and registered adapters', function (): void {
    $registered = new SinglePassAdapter(single(...));
    $factory = factory(true, ['registered' => $registered]);
    $group = new MiddlewareGroup(
        __NAMESPACE__ . '\\single',
        double(...),
        new Methods(),
        static fn (#[CurrentRequest] ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface =>
            $next->handle($request)->withAddedHeader('X-Layer', 'psr15'),
    );
    expect($factory->createMiddleware('registered'))->toBe($registered);
    $middleware = $factory->createMiddleware($group);
    foreach (['/first', '/second'] as $path) {
        $response = $middleware->process(new ServerRequest('GET', $path), terminal());
        expect($response->getStatusCode())->toBe(202)
            ->and((string) $response->getBody())->toBe($path)
            ->and($response->getHeader('X-Layer'))->toBe(['psr15', 'single', 'double', 'single']);
    }
});

it('keeps the core factory behavior unchanged when legacy resolvers are not registered', function (): void {
    $middleware = factory(false)->createMiddleware(single(...));
    expect($middleware)->not->toBeInstanceOf(SinglePassAdapter::class);
});
