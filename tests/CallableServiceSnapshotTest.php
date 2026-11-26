<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\Snapshot;

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\CallableExecutorInterface;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Middleware\MiddlewareGroup;
use Componenta\Http\Middleware\Adapter\CallableAdapter;
use Componenta\Http\Middleware\Resolver\CallableResolver;
use Componenta\Http\Middleware\Resolver\CompositeResolver;
use Componenta\Http\Middleware\Resolver\ServiceResolver;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class OriginalTarget
{
    public static function run(): ResponseInterface
    {
        return new Response(201);
    }
}

final class ReplacementTarget
{
    public static function run(): ResponseInterface
    {
        return new Response(202);
    }
}

it('selects a callable before autoload code can change referenced receiver slots', function (bool $throughService): void {
    $alias = __NAMESPACE__ . '\\Pending_' . bin2hex(random_bytes(8));
    $owner = $alias;
    $method = 'run';
    $entry = [&$owner, &$method];
    $services = new class($entry) implements ContainerInterface {
        public function __construct(private array $entry) {}
        public function has(string $id): bool { return $id === 'snapshot'; }
        public function get(string $id): mixed { return $this->entry; }
    };
    $composition = (new ConfigFactory())->create(new Environment([]));
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = new MiddlewareFactory(new CompositeResolver(), new ServiceResolver($services), new CallableResolver($container->get(CallableExecutorInterface::class)));
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface { return new Response(204); }
    };
    $loads = 0;
    $loader = static function (string $class) use ($alias, &$owner, &$loads): void {
        if ($class === $alias) {
            ++$loads;
            class_alias(OriginalTarget::class, $alias);
            $owner = ReplacementTarget::class;
        }
    };
    spl_autoload_register($loader);
    try {
        $middleware = $throughService
            ? $factory->createMiddleware('snapshot')
            : CallableAdapter::fromCallable($entry, $container->get(CallableExecutorInterface::class));
        $request = new ServerRequest('GET', '/');

        expect($owner)->toBe(ReplacementTarget::class)
            ->and($loads)->toBe(1)
            ->and($middleware->process($request, $terminal)->getStatusCode())->toBe(201)
            ->and($middleware->process($request, $terminal)->getStatusCode())->toBe(201);
    } finally {
        spl_autoload_unregister($loader);
    }
})->with(['service entry' => [true], 'explicit adapter' => [false]]);


it('preserves the selected callable pair when group definitions are created or extended', function (string $mode): void {
    $owner = OriginalTarget::class;
    $method = 'run';
    $definition = [&$owner, &$method];

    $first = static fn (): ResponseInterface => new Response(204);
    $base = new MiddlewareGroup($first);
    $group = match ($mode) {
        'constructor' => new MiddlewareGroup($definition),
        'from array' => MiddlewareGroup::fromArray([$definition]),
        'with' => $base->with($definition, prepend: true),
        'with many' => $base->withMany([$definition], prepend: true),
        'generator' => $base->withMany((static function () use ($definition, &$owner): \Generator {
            yield $definition;
            $owner = ReplacementTarget::class;
        })(), prepend: true),
    };
    $owner = ReplacementTarget::class;

    $composition = (new ConfigFactory())->create(new Environment([]));
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = new MiddlewareFactory(new CompositeResolver(), null, new CallableResolver($container->get(CallableExecutorInterface::class)));
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface { return new Response(500); }
    };

    $middleware = $factory->createMiddleware($group);
    expect($group->toArray()[0])->toBe([OriginalTarget::class, 'run'])
        ->and($middleware->process(new ServerRequest('GET', '/'), $terminal)->getStatusCode())->toBe(201);
})->with(['constructor', 'from array', 'with', 'with many', 'generator']);
