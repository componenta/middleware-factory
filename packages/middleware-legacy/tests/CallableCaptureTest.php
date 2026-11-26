<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Legacy\Tests\Capture;

use Componenta\Http\Middleware\Legacy\SinglePassAdapter;
use Componenta\Http\Middleware\Legacy\DoublePassAdapter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class OriginalTarget
{
    public static function run(): ResponseInterface { return new Response(201); }
    public function instance(): ResponseInterface { return new Response(201); }
    public function __invoke(): ResponseInterface { return new Response(201); }
}
final class ReplacementTarget
{
    public static function run(): ResponseInterface { return new Response(202); }
}
function callback(): ResponseInterface
{
    return new Response(201);
}
function terminal(): RequestHandlerInterface
{
    return new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface { return new Response(500); }
    };
}

it('accepts public native callable forms', function (bool $double, string $form): void {
    $target = new OriginalTarget();
    $callback = match ($form) {
        'closure' => static fn (): ResponseInterface => new Response(201),
        'function' => __NAMESPACE__ . '\\callback',
        'static string' => OriginalTarget::class . '::run',
        'static pair' => [OriginalTarget::class, 'run'],
        'instance pair' => [$target, 'instance'],
        'invokable' => $target,
    };
    $middleware = $double ? new DoublePassAdapter($callback, new Psr17Factory()) : new SinglePassAdapter($callback);

    expect($middleware->process(new ServerRequest('GET', '/'), terminal())->getStatusCode())->toBe(201);
})->with(['single-pass' => [false], 'double-pass' => [true]])
    ->with(['closure', 'function', 'static string', 'static pair', 'instance pair', 'invokable']);

it('captures referenced method slots before autoload can retarget a callable', function (bool $double): void {
    $alias = __NAMESPACE__ . '\\Pending_' . bin2hex(random_bytes(8));
    $owner = $alias;
    $method = 'run';
    $callback = [&$owner, &$method];
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
        $middleware = $double ? new DoublePassAdapter($callback, new Psr17Factory()) : new SinglePassAdapter($callback);
        expect($owner)->toBe(ReplacementTarget::class)
            ->and($loads)->toBe(1)
            ->and($middleware->process(new ServerRequest('GET', '/'), terminal())->getStatusCode())->toBe(201)
            ->and($middleware->process(new ServerRequest('GET', '/'), terminal())->getStatusCode())->toBe(201);
    } finally {
        spl_autoload_unregister($loader);
    }
})->with(['single-pass' => [false], 'double-pass' => [true]]);

it('rejects malformed and inaccessible callable arrays at construction', function (bool $double): void {
    $target = new class {
        private function hidden(): ResponseInterface { return new Response(201); }
    };

    foreach ([[$target, 'hidden'], [$target, 'missing'], [OriginalTarget::class, 'run', 'extra']] as $callback) {
        expect(fn () => $double ? new DoublePassAdapter($callback, new Psr17Factory()) : new SinglePassAdapter($callback))
            ->toThrow(\InvalidArgumentException::class);
    }
})->with(['single-pass' => [false], 'double-pass' => [true]]);
