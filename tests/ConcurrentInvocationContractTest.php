<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\ConcurrentInvocation;

use Attribute;
use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\Attribute\CurrentRequest;
use Componenta\DI\Attribute\Header;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\ConfigProvider;
use Componenta\Http\Middleware\MiddlewareFactory;
use Fiber;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class SuspendingHeader extends Header
{
    public function extract(ServerRequestInterface $request): mixed
    {
        $value = parent::extract($request);
        if (Fiber::getCurrent() !== null) {
            Fiber::suspend($value);
        }

        return $value;
    }
}

final class Callback
{
    public static function run(
        #[SuspendingHeader('X-Input')] string $value,
        #[CurrentRequest] ServerRequestInterface $request,
        RequestHandlerInterface $next,
    ): ResponseInterface {
        return $next->handle($request)->withHeader('X-Resolved', $value);
    }
}

function terminal(string $label): RequestHandlerInterface
{
    return new class($label) implements RequestHandlerInterface {
        public int $calls = 0;

        public function __construct(private readonly string $label) {}

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            ++$this->calls;

            return new Response(202, [], $this->label . ':' . $request->getUri()->getPath());
        }
    };
}

it('isolates overlapping DI parameter resolution and continuations on a shared middleware', function (bool $closure): void {
    $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider());
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
    $factory = $container->get(MiddlewareFactory::class);
    $middleware = $factory->createMiddleware($closure ? Callback::run(...) : [Callback::class, 'run']);

    // Populate the named callable's prepared parameter plan, when cacheable.
    $warm = $middleware->process(new ServerRequest('GET', '/warm', ['X-Input' => 'warm']), terminal('warm'));
    expect($warm->getHeaderLine('X-Resolved'))->toBe('warm')
        ->and((string) $warm->getBody())->toBe('warm:/warm');

    $firstTerminal = terminal('first');
    $secondTerminal = terminal('second');
    $first = new Fiber(fn () => $middleware->process(
        new ServerRequest('GET', '/first', ['X-Input' => 'alpha']),
        $firstTerminal,
    ));
    $second = new Fiber(fn () => $middleware->process(
        new ServerRequest('GET', '/second', ['X-Input' => 'beta']),
        $secondTerminal,
    ));

    expect($first->start())->toBe('alpha')
        ->and($second->start())->toBe('beta')
        ->and($firstTerminal->calls)->toBe(0)
        ->and($secondTerminal->calls)->toBe(0);

    $second->resume();
    $first->resume();

    $firstResponse = $first->getReturn();
    $secondResponse = $second->getReturn();

    expect($firstResponse->getHeaderLine('X-Resolved'))->toBe('alpha')
        ->and((string) $firstResponse->getBody())->toBe('first:/first')
        ->and($secondResponse->getHeaderLine('X-Resolved'))->toBe('beta')
        ->and((string) $secondResponse->getBody())->toBe('second:/second')
        ->and($firstTerminal->calls)->toBe(1)
        ->and($secondTerminal->calls)->toBe(1);
})->with(['closure' => [true], 'named method' => [false]]);
