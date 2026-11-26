<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Legacy\Tests\Signature;

use Closure;
use Componenta\Http\Middleware\Legacy\Internal\LegacySignature;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

it('recognizes only explicit legacy signatures without invoking callbacks', function (bool $double, string $continuation): void {
    $callback = match ([$double, $continuation]) {
        [false, 'callable'] => static fn (ServerRequestInterface $request, callable $next): never => throw new \LogicException('not invoked'),
        [false, 'closure'] => static fn (ServerRequestInterface $request, Closure $next): never => throw new \LogicException('not invoked'),
        [true, 'callable'] => static fn (ServerRequestInterface $request, ResponseInterface $response, callable $next): never => throw new \LogicException('not invoked'),
        [true, 'closure'] => static fn (ServerRequestInterface $request, ResponseInterface $response, Closure $next): never => throw new \LogicException('not invoked'),
    };

    expect(LegacySignature::recognize($callback, $double))->toBe($callback)
        ->and(LegacySignature::recognize($callback, !$double))->toBeNull();
})->with(['single' => [false], 'double' => [true]])->with(['callable', 'closure']);

it('does not infer a legacy convention from ambiguous or incompatible signatures', function (Closure $callback): void {
    expect(LegacySignature::recognize($callback, false))->toBeNull()
        ->and(LegacySignature::recognize($callback, true))->toBeNull();
})->with([
    'PSR-15 signature' => [static fn (ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface => new Response()],
    'untyped parameters' => [static fn ($request, $next): ResponseInterface => new Response()],
    'concrete request' => [static fn (\Nyholm\Psr7\ServerRequest $request, callable $next): ResponseInterface => new Response()],
    'concrete response' => [static fn (ServerRequestInterface $request, Response $response, callable $next): ResponseInterface => new Response()],
    'ambiguous continuation' => [static fn (ServerRequestInterface $request, callable|RequestHandlerInterface $next): ResponseInterface => new Response()],
    'reference parameter' => [static fn (ServerRequestInterface &$request, callable $next): ResponseInterface => new Response()],
    'variadic continuation' => [static fn (ServerRequestInterface $request, callable ...$next): ResponseInterface => new Response()],
    'extra parameter' => [static fn (ServerRequestInterface $request, callable $next, string $extra = ''): ResponseInterface => new Response()],
]);
