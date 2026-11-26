<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\CallableResult;

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\Attribute\CurrentRequest;
use Componenta\DI\CallableExecutorInterface;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\Adapter\CallableAdapter;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

function executor(): CallableExecutorInterface
{
    $composition = (new ConfigFactory())->create(new Environment([]));
    return (new ContainerFactory())->create($composition->config, $composition->dependencies)->container->get(CallableExecutorInterface::class);
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

it('rejects an indirect callable result cycle before invoking a visited adapter again', function (): void {
    $executor = executor();
    $first = null;
    $second = null;
    $cycle = true;
    $calls = [];

    $first = CallableAdapter::fromCallable(
        static function () use (&$calls, &$second, &$cycle): mixed {
            $calls[] = 'first';
            if (count($calls) > 2) {
                throw new \LogicException('The test recursion limit was reached.');
            }
            return $cycle ? $second : new Response(201);
        },
        $executor,
    );
    $second = CallableAdapter::fromCallable(
        static function () use (&$calls, &$first): mixed {
            $calls[] = 'second';
            return $first;
        },
        $executor,
    );

    $request = new ServerRequest('GET', '/');
    expect(fn () => $first->process($request, terminal()))
        ->toThrow(\UnexpectedValueException::class, 'Cyclic callable middleware result')
        ->and($calls)->toBe(['first', 'second']);

    $cycle = false;
    $calls = [];
    expect($first->process($request, terminal())->getStatusCode())->toBe(201)
        ->and($calls)->toBe(['first']);
});

it('preserves each returned adapter normalizer and forwards the original HTTP context', function (): void {
    $executor = executor();
    $calls = [];
    $last = CallableAdapter::fromCallable(
        static function (#[CurrentRequest] ServerRequestInterface $request, RequestHandlerInterface $next) use (&$calls): string {
            $calls[] = 'last';
            return (string) $next->handle($request)->getBody();
        },
        $executor,
        static fn (mixed $value): ResponseInterface => new Response(201, [], 'last:' . $value),
    );
    $middle = CallableAdapter::fromCallable(
        static function () use ($last, &$calls): MiddlewareInterface {
            $calls[] = 'middle';
            return $last;
        },
        $executor,
        static fn (mixed $value): never => throw new \LogicException('The intermediate normalizer must not run.'),
    );
    $first = CallableAdapter::fromCallable(
        static function () use ($middle, &$calls): MiddlewareInterface {
            $calls[] = 'first';
            return $middle;
        },
        $executor,
    );

    $request = new ServerRequest('GET', '/context');
    for ($i = 0; $i < 2; ++$i) {
        $response = $first->process($request, terminal());
        expect($response->getStatusCode())->toBe(201)
            ->and((string) $response->getBody())->toBe('last:/context');
    }
    expect($calls)->toBe(['first', 'middle', 'last', 'first', 'middle', 'last']);
});
