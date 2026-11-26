<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\CallableForms;

use Componenta\Config\ConfigFactory;
use Componenta\Config\ConfigKey;
use Componenta\Config\Environment;
use Componenta\DI\Attribute\CurrentRequest;
use Componenta\DI\Attribute\Header;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\ConfigProvider;
use Componenta\Http\Middleware\MiddlewareFactory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class Dependency
{
    public function __construct(public string $value) {}
}

function result(ServerRequestInterface $request, RequestHandlerInterface $next, string $value, Dependency $dependency): ResponseInterface
{
    return $next->handle($request)
        ->withHeader('X-Resolved', $dependency->value . ':' . $value);
}

function middlewareFunction(
    #[CurrentRequest] ServerRequestInterface $request,
    RequestHandlerInterface $next,
    #[Header('X-Input')] string $value,
    Dependency $dependency,
): ResponseInterface {
    return result($request, $next, $value, $dependency);
}

final class MiddlewareMethods
{
    public static function run(
        #[CurrentRequest] ServerRequestInterface $request,
        RequestHandlerInterface $next,
        #[Header('X-Input')] string $value,
        Dependency $dependency,
    ): ResponseInterface {
        return result($request, $next, $value, $dependency);
    }

    public function instance(
        #[CurrentRequest] ServerRequestInterface $request,
        RequestHandlerInterface $next,
        #[Header('X-Input')] string $value,
        Dependency $dependency,
    ): ResponseInterface {
        return result($request, $next, $value, $dependency);
    }

    public function __invoke(
        #[CurrentRequest] ServerRequestInterface $request,
        RequestHandlerInterface $next,
        #[Header('X-Input')] string $value,
        Dependency $dependency,
    ): ResponseInterface {
        return result($request, $next, $value, $dependency);
    }
}

it('preserves DI injection and request isolation for supported callable forms', function (string $form): void {
    $object = new MiddlewareMethods();
    $function = __NAMESPACE__ . '\\middlewareFunction';
    $static = MiddlewareMethods::class . '::run';
    $composition = (new ConfigFactory())->create(
        new Environment([]),
        new ConfigProvider(),
        static fn (): array => [ConfigKey::DEPENDENCIES => [ConfigKey::SERVICES => [
            Dependency::class => new Dependency('dependency'),
            MiddlewareMethods::class => $object,
            'method.receiver' => $object,
            'function.service' => $function,
            'static.service' => $static,
            'method.service' => [$object, 'instance'],
        ]]],
    );
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = $container->get(MiddlewareFactory::class);
    $definition = match ($form) {
        'closure' => middlewareFunction(...),
        'function' => $function,
        'static string' => $static,
        'static pair' => [MiddlewareMethods::class, 'run'],
        'instance string' => MiddlewareMethods::class . '::instance',
        'instance pair' => [$object, 'instance'],
        'invokable object' => $object,
        'invokable class service' => MiddlewareMethods::class,
        'receiver service pair' => ['method.receiver', 'instance'],
        'function service' => 'function.service',
        'static service' => 'static.service',
        'method service' => 'method.service',
    };
    $middleware = $factory->createMiddleware($definition);
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(202, [], $request->getUri()->getPath());
        }
    };

    foreach (['first', 'second'] as $value) {
        $response = $middleware->process(new ServerRequest('GET', '/' . $value, ['X-Input' => $value]), $terminal);
        expect($response->getStatusCode())->toBe(202)
            ->and((string) $response->getBody())->toBe('/' . $value)
            ->and($response->getHeaderLine('X-Resolved'))->toBe('dependency:' . $value);
    }
})->with([
    'closure',
    'function',
    'static string',
    'static pair',
    'instance string',
    'instance pair',
    'invokable object',
    'invokable class service',
    'receiver service pair',
    'function service',
    'static service',
    'method service',
]);
