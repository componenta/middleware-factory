<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests;

use Componenta\DI\CallableExecutorInterface;
use Componenta\Http\Middleware\Adapter\CallableAdapter;
use Componenta\Http\Middleware\Exception\MiddlewareResolutionException;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareGroup;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\CompositeResolver;
use Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface;
use Componenta\Http\Middleware\Resolver\ServiceResolver;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

function terminal(): RequestHandlerInterface
{
    return new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(204, [], $request->getUri()->getPath());
        }
    };
}

function passThrough(): MiddlewareInterface
{
    return new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            return $handler->handle($request);
        }
        public function __invoke(): never { throw new \LogicException('must not invoke'); }
    };
}

function services(array $entries): ContainerInterface
{
    return new class($entries) implements ContainerInterface {
        public function __construct(private array $entries) {}
        public function has(string $id): bool { return array_key_exists($id, $this->entries); }
        public function get(string $id): mixed { return $this->entries[$id]; }
    };
}

function executor(): CallableExecutorInterface
{
    return new class implements CallableExecutorInterface {
        public function resolve(mixed $callable): callable
        {
            if (!is_callable($callable)) {
                throw new \InvalidArgumentException('not callable');
            }
            return $callable;
        }
        public function call(mixed $callable, array $params = []): mixed
        {
            return $callable(...array_values($params));
        }
    };
}

it('recognizes PSR-15 objects before invoking any extension resolver', function () {
    $reject = new class implements MiddlewareResolverInterface {
        public function resolve(mixed $middleware): ?MiddlewareInterface { throw new \LogicException('not reached'); }
    };
    $factory = new MiddlewareFactory($reject);
    $middleware = passThrough();

    expect($factory->createMiddleware($middleware))->toBe($middleware)
        ->and($factory->createMiddleware(terminal())->process(new ServerRequest('GET', '/'), terminal())->getStatusCode())->toBe(204);
});

it('resolves opaque service ids and rejects invalid present entries', function () {
    $middleware = passThrough();
    $factory = new MiddlewareFactory(new CompositeResolver(), new ServiceResolver(services([
        'opaque.id' => $middleware,
        'strlen' => 42,
    ])), new CallableResolver(executor()));

    expect($factory->createMiddleware('opaque.id'))->toBe($middleware)
        ->and(fn () => $factory->createMiddleware('strlen'))->toThrow(MiddlewareResolutionException::class);
});

it('resolves nested groups and allows repeated use of the same group', function () {
    $group = new MiddlewareGroup(passThrough());
    $factory = new MiddlewareFactory(new CompositeResolver(), new ServiceResolver(services([
        'shared' => $group,
        'outer' => new MiddlewareGroup('shared', 'shared'),
    ])));
    $middleware = $factory->createMiddleware('outer');

    expect((string) $middleware->process(new ServerRequest('GET', '/nested'), terminal())->getBody())->toBe('/nested');
});

it('rejects service-group cycles without poisoning subsequent resolutions', function () {
    $middleware = passThrough();
    $factory = new MiddlewareFactory(new CompositeResolver(), new ServiceResolver(services([
        'a' => new MiddlewareGroup('b'),
        'b' => new MiddlewareGroup('a'),
        'valid' => $middleware,
    ])));

    expect(fn () => $factory->createMiddleware('a'))->toThrow(MiddlewareResolutionException::class)
        ->and($factory->createMiddleware('valid'))->toBe($middleware);
});

it('normalizes associative groups to ordered lists and preserves originals', function () {
    $group = MiddlewareGroup::fromArray(['first' => 'a', 'middleware' => 'b', 1 => 'c']);
    $next = $group->withMany(['d', 'e'], prepend: true);
    expect($group->toArray())->toBe(['a', 'b', 'c'])
        ->and($next->toArray())->toBe(['d', 'e', 'a', 'b', 'c'])
        ->and(iterator_to_array($next))->toBe($next->toArray())
        ->and(fn () => $group->withMany([]))->toThrow(\InvalidArgumentException::class);
});

it('keeps extension resolver chains immutable and shared factories independent', function () {
    $first = passThrough();
    $second = passThrough();
    $resolver = static fn (MiddlewareInterface $result): MiddlewareResolverInterface => new class($result) implements MiddlewareResolverInterface {
        public function __construct(private MiddlewareInterface $result) {}
        public function resolve(mixed $middleware): ?MiddlewareInterface { return $this->result; }
    };
    $original = new CompositeResolver($resolver($first));
    $extended = $original->with($resolver($second), prepend: true);

    expect((new MiddlewareFactory($original))->createMiddleware('x'))->toBe($first)
        ->and((new MiddlewareFactory($extended))->createMiddleware('x'))->toBe($second)
        ->and((new MiddlewareFactory($original))->createMiddleware('x'))->toBe($first);
});

it('requires explicit response normalization and preserves the original failure', function () {
    $callable = static fn (): string => 'result';
    $request = new ServerRequest('GET', '/');
    $plain = CallableAdapter::fromCallable($callable, executor());
    expect(fn () => $plain->process($request, terminal()))->toThrow(\UnexpectedValueException::class);

    $failure = new \DomainException('serialization failed');
    $normalizer = static function (mixed $value) use ($failure): ResponseInterface { throw $failure; };
    $adapter = CallableAdapter::fromCallable($callable, executor(), $normalizer);
    $caught = null;
    try {
        $adapter->process($request, terminal());
    } catch (\DomainException $e) {
        $caught = $e;
    }
    expect($caught)->toBe($failure);
});

it('retains exceptions with non-integer SQLSTATE codes without exposing raw definitions', function () {
    $failure = new class extends \RuntimeException {
        protected $code = 'HY000';
    };
    $exception = MiddlewareResolutionException::fromPrevious(new \stdClass(), $failure);

    expect($exception->getPrevious())->toBe($failure)
        ->and($exception->getCode())->toBe(0)
        ->and($exception->definitionType)->toBe('stdClass');
});
