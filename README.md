# Componenta Middleware Factory

Resolves trusted middleware definitions into PSR-15 middleware. The factory is independent of HTTP response serialization; execution of middleware chains belongs to [Componenta Pipeline](https://github.com/componenta/pipeline).

## Installation

```sh
composer require componenta/middleware-factory:^3.0
```

Requires PHP 8.5.13+, Config 4, DI 6 and Pipeline 1.1.1. The package registers its configuration provider through `extra.componenta.config-providers`. Applications without the Componenta Composer plugin can compose `Componenta\Http\Middleware\ConfigProvider` explicitly.

## Definitions

```php
use Componenta\DI\Attribute\CurrentRequest;
use Componenta\Http\Middleware\MiddlewareGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

$auth = $factory->createMiddleware(AuthMiddleware::class);
$service = $factory->createMiddleware('middleware.auth');

$callback = static function (
    #[CurrentRequest] ServerRequestInterface $request,
    RequestHandlerInterface $next,
): ResponseInterface {
    return $next->handle($request)->withHeader('X-Processed', 'yes');
};

$group = new MiddlewareGroup($auth, $callback);
$middleware = $factory->createMiddleware($group);
$response = $middleware->process($request, $terminal);
```

Resolution order:

1. Existing PSR-15 middleware is returned unchanged.
2. Existing request handlers are adapted as middleware.
3. Middleware groups are recursively resolved in order.
4. Configured extension resolvers are tried in order. The first non-null result wins.
5. String service identifiers are looked up in the PSR-11 container. A present service must provide middleware, a request handler, a group or a native callable. Errors do not fall through to another interpretation of the identifier.
6. Remaining callable definitions are resolved through DI. Supported forms follow DI's callable resolution contract.

The default group factory uses `PipelineFactoryInterface`. To supply a custom implementation, override its alias in application dependency configuration after the package providers.

A group is a non-empty ordered collection, not a name. Register a group under a service identifier to reference it by name. Groups accept arbitrary array or iterable keys, normalize to a list, and preserve value order. `with()` and `withMany()` return new groups; the original collection is unchanged.

## Callable middleware

Callable middleware requires the DI `CallableExecutorInterface`, not a native positional invoker. The current HTTP request is supplied as request context; use `#[CurrentRequest]` to inject it, and the ordinary DI HTTP attributes to extract headers, query values or payload data. The next handler is supplied by `RequestHandlerInterface` type. A bare request type does not automatically select the current HTTP request in DI 6.

By default, a callable must return one of:

- `ResponseInterface` (returned unchanged).
- `MiddlewareInterface` (processed with the same request and continuation).
- `RequestHandlerInterface` (handled directly with the same request).

Other return values cause `UnexpectedValueException`. A normalization closure may be explicitly configured on `CallableResolver` or passed to `CallableAdapter::fromCallable()`. Its result must be a PSR-7 response; failures propagate unchanged. There is no implicit responder service lookup. To configure normalization for the default factory, replace the `CallableResolver::class` entry in `Componenta\Config\ConfigKey::FACTORIES` after the package provider. Do not add a second binding in `SERVICES`.

Request handlers are terminal by default, but an adapted request handler may explicitly delegate using the request attribute `RequestHandlerAdapter::FALLBACK_HANDLER_ATTRIBUTES_KEY`. The original request is not modified. Do not retain the request or its continuation beyond processing. Direct calls to an adapter's `handle()` do not add this attribute.

## Extension resolvers

Implement `Componenta\Http\Middleware\Resolver\MiddlewareResolverInterface`. Return `null` only for an unhandled definition; throw on an attempted resolution failure. Register the resolver as a container service and list its identifier in application configuration:

```php
use Componenta\Http\Middleware\ConfigKey;

return [
    ConfigKey::RESOLVERS => [CustomMiddlewareResolver::class],
];
```

Array definition slots are captured before resolution, including before extension resolvers execute. Definition objects retain their identity; nested custom payloads are not deep-frozen.

The extension chain is immutable. `CompositeResolver::with()` creates a new chain; a previously constructed factory is not modified. Extension resolvers are not invoked for existing PSR-15 objects or groups.

Resolution failures implement `Componenta\Http\Middleware\Exception\MiddlewareResolutionExceptionInterface`. Its diagnostic `definitionType` describes the input type without retaining raw definitions. The original cause is available through `getPrevious()`. Do not expose exception chains to HTTP clients.

Only trusted application configuration and code should supply middleware definitions. Do not use untrusted HTTP request data as a service identifier or callable.

## Development

```sh
composer test
composer phpstan
composer benchmark
```

See [migration instructions](MIGRATION.md) for upgrading from 2.x.
