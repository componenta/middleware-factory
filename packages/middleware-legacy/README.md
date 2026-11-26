# Componenta Middleware Legacy

Explicit, optional adapters for the former Componenta single-pass and double-pass callable middleware conventions. Both adapters implement PSR-15 MiddlewareInterface and require only PSR packages at runtime. There is no automatic signature detection, container lookup, response normalization or dependency on Componenta DI, Pipeline or Middleware Factory.

## Installation

Requires PHP 8.4 or 8.5. Install the package through Composer after it has been published:

```sh
composer require componenta/middleware-legacy
```

## Single-pass

```php
use Componenta\Http\Middleware\Legacy\SinglePassAdapter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$middleware = new SinglePassAdapter(
    static function (ServerRequestInterface $request, callable $next): ResponseInterface {
        return $next($request->withAttribute('checked', true))
            ->withHeader('X-Processed', 'yes');
    },
);
```

The callback receives the current request and a continuation accepting a request. It can call the continuation, return its own response, or modify the returned response.

## Double-pass

```php
use Componenta\Http\Middleware\Legacy\DoublePassAdapter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$middleware = new DoublePassAdapter(
    static function (
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next,
    ): ResponseInterface {
        if ($request->getMethod() === 'OPTIONS') {
            return $response->withStatus(204);
        }

        return $next($request)->withHeader('X-Processed', 'yes');
    },
    $responseFactory,
);
```

The second constructor argument is a PSR-17 ResponseFactoryInterface. A new seed response is requested for every invocation.

**Compatibility boundary:** this is the former Componenta double-pass convention, whose continuation accepts only the request. PSR-15 RequestHandlerInterface cannot accept a seed response. Calls such as $next($request, $response) are explicitly rejected with LogicException before delegation, rather than silently dropping response changes. Rewrite those callbacks to modify the downstream response after calling $next($request), or return the seed response to terminate the chain. This is not a transparent adapter for a response-threading double-pass runtime.

## Contracts

Pass native PHP callables: closures, public functions, public method pairs or invokable objects. Method-pair array slots are captured before validation; object identity is preserved. The callback must return ResponseInterface. Other return values cause UnexpectedValueException. Callback and downstream exceptions propagate unchanged. Extra dependencies can be captured in closures or supplied through callable objects; the adapters do not perform DI. Do not retain the continuation beyond request processing.

## Integration

The adapters can be passed directly to Pipeline::pipe() or MiddlewareFactory::createMiddleware(), just like any other MiddlewareInterface. Those packages do not depend on or detect this optional package.

```php
$pipeline = $pipeline->pipe($middleware);
$resolved = $factory->createMiddleware($middleware);
```

## Development

```sh
composer install
composer check
composer audit --locked --no-dev
```

See [Russian documentation](README.ru.md).
