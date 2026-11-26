<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Legacy\Tests;

use Componenta\Http\Middleware\Legacy\DoublePassAdapter;
use Componenta\Http\Middleware\Legacy\SinglePassAdapter;
use Componenta\Http\Middleware\Pipeline;
use DomainException;
use Fiber;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;

function terminal(): RequestHandlerInterface
{
    return new class implements RequestHandlerInterface {
        public int $calls = 0;
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            ++$this->calls;
            return new Response(202, [], $request->getUri()->getPath());
        }
    };
}

function adapter(bool $double, callable $callback): MiddlewareInterface
{
    return $double
        ? new DoublePassAdapter($callback, new Psr17Factory())
        : new SinglePassAdapter($callback);
}

it('forwards the modified request and returns the response with post-processing', function (bool $double): void {
    $callback = static function (ServerRequestInterface $request, callable $next): ResponseInterface {
        return $next($request->withUri($request->getUri()->withPath('/changed')))
            ->withHeader('X-Processed', 'yes');
    };
    $middleware = adapter($double, $double
        ? static fn (ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface => $callback($request, $next)
        : $callback);
    $next = terminal();

    $response = $middleware->process(new ServerRequest('GET', '/original'), $next);

    expect($response->getStatusCode())->toBe(202)
        ->and((string) $response->getBody())->toBe('/changed')
        ->and($response->getHeaderLine('X-Processed'))->toBe('yes')
        ->and($next->calls)->toBe(1);
})->with(['single-pass' => [false], 'double-pass' => [true]]);

it('returns the exact short-circuit response without running the continuation', function (bool $double): void {
    $response = new Response(403);
    $next = terminal();

    expect(adapter($double, static fn (): ResponseInterface => $response)->process(new ServerRequest('GET', '/'), $next))
        ->toBe($response)
        ->and($next->calls)->toBe(0);
})->with(['single-pass' => [false], 'double-pass' => [true]]);

it('rejects invalid callback results without implicit normalization', function (bool $double, mixed $value): void {
    $middleware = adapter($double, static fn (): mixed => $value);
    $next = terminal();
    expect(fn () => $middleware->process(new ServerRequest('GET', '/'), $next))
        ->toThrow(UnexpectedValueException::class, 'must return ResponseInterface')
        ->and($next->calls)->toBe(0);
})->with(['single-pass' => [false], 'double-pass' => [true]])
    ->with(['null' => [null], 'scalar' => ['body'], 'array' => [[1]], 'object' => [new \stdClass()]]);

it('preserves exceptions from the callback or continuation', function (bool $double, bool $downstream): void {
    $failure = new DomainException('original error');
    $next = new class($failure) implements RequestHandlerInterface {
        public function __construct(private DomainException $failure) {}
        public function handle(ServerRequestInterface $request): ResponseInterface { throw $this->failure; }
    };
    $callback = static function (ServerRequestInterface $request, callable $next) use ($failure, $downstream): ResponseInterface {
        if (!$downstream) {
            throw $failure;
        }
        return $next($request);
    };
    $middleware = adapter($double, $double
        ? static fn (ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface => $callback($request, $next)
        : $callback);

    $caught = null;
    try {
        $middleware->process(new ServerRequest('GET', '/'), $next);
    } catch (DomainException $error) {
        $caught = $error;
    }

    expect($caught)->toBe($failure);
})->with(['single-pass' => [false], 'double-pass' => [true]])
    ->with(['callback' => [false], 'continuation' => [true]]);

it('requests a fresh seed response for each invocation and propagates factory failures', function (): void {
    $factory = new class implements ResponseFactoryInterface {
        public int $calls = 0;
        public ?DomainException $failure = null;

        public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
        {
            ++$this->calls;
            if ($this->failure !== null) {
                throw $this->failure;
            }
            return new Response($code);
        }
    };
    $middleware = new DoublePassAdapter(
        static fn (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface => $response,
        $factory,
    );
    $next = terminal();
    $first = $middleware->process(new ServerRequest('GET', '/first'), $next);
    $first->getBody()->write('modified');
    $second = $middleware->process(new ServerRequest('GET', '/second'), $next);

    expect($factory->calls)->toBe(2)
        ->and($first)->not->toBe($second)
        ->and((string) $second->getBody())->toBe('')
        ->and($next->calls)->toBe(0);

    $factory->failure = new DomainException('factory failure');
    $caught = null;
    try {
        $middleware->process(new ServerRequest('GET', '/fail'), $next);
    } catch (DomainException $error) {
        $caught = $error;
    }
    expect($caught)->toBe($factory->failure);
});

it('rejects an attempt to thread a seed response through a PSR-15 continuation', function (bool $modified): void {
    $middleware = new DoublePassAdapter(
        static fn (ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface =>
            $next($request, $modified ? $response->withHeader('X-Legacy', 'yes') : $response),
        new Psr17Factory(),
    );
    $next = terminal();
    expect(fn () => $middleware->process(new ServerRequest('GET', '/'), $next))
        ->toThrow(LogicException::class, 'cannot forward a seed response')
        ->and($next->calls)->toBe(0);
})->with(['original seed' => [false], 'modified seed' => [true]]);

it('isolates suspended calls using a shared adapter and separate continuations', function (bool $double): void {
    $callback = static function (ServerRequestInterface $request, callable $next): ResponseInterface {
        Fiber::suspend($request->getUri()->getPath());
        return $next($request);
    };
    $middleware = adapter($double, $double
        ? static fn (ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface => $callback($request, $next)
        : $callback);
    $firstNext = terminal();
    $secondNext = terminal();
    $first = new Fiber(fn () => $middleware->process(new ServerRequest('GET', '/first'), $firstNext));
    $second = new Fiber(fn () => $middleware->process(new ServerRequest('GET', '/second'), $secondNext));

    expect($first->start())->toBe('/first')->and($second->start())->toBe('/second');
    $second->resume();
    $first->resume();

    expect((string) $first->getReturn()->getBody())->toBe('/first')
        ->and((string) $second->getReturn()->getBody())->toBe('/second')
        ->and($firstNext->calls)->toBe(1)
        ->and($secondNext->calls)->toBe(1);
})->with(['single-pass' => [false], 'double-pass' => [true]]);

it('composes both adapters in a standard published PSR-15 pipeline', function (): void {
    $single = new SinglePassAdapter(
        static fn (ServerRequestInterface $request, callable $next): ResponseInterface => $next($request)->withAddedHeader('X-Layer', 'single'),
    );
    $double = new DoublePassAdapter(
        static fn (ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface => $next($request)->withAddedHeader('X-Layer', 'double'),
        new Psr17Factory(),
    );
    $next = terminal();
    $pipeline = new Pipeline([$single, $double], $next);

    foreach (['/first', '/second'] as $path) {
        $response = $pipeline->handle(new ServerRequest('GET', $path));
        expect((string) $response->getBody())->toBe($path)
            ->and($response->getHeader('X-Layer'))->toBe(['double', 'single']);
    }
    expect($next->calls)->toBe(2);
});
