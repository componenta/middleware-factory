# Componenta Middleware Factory

PSR-15 middleware resolver and group factory.

Use this package when routes, modules, or config files need to describe middleware as class names, service ids, callables, existing middleware instances, or named groups, while the runtime always receives concrete `MiddlewareInterface` instances.

## Installation

```bash
composer require componenta/middleware-factory
```

The package declares `Componenta\Http\Middleware\ConfigProvider` in `extra.componenta.config-providers`.
When `componenta/composer-plugin` is installed, the provider is added to the generated provider list automatically.

## Requirements

- PHP 8.4+
- PSR-11 container
- PSR-15 HTTP server middleware

## Related Packages

| Package | Why it matters here |
|---|---|
| `componenta/router` | Stores route middleware as strings, classes, or groups and passes them to this factory. |
| `componenta/pipeline` | Executes created middleware as a PSR-15 chain. |
| `componenta/di` | Resolves middleware by service id or class name. |
| `componenta/http-*-middleware` | Provides concrete HTTP middleware packages that this factory can resolve through attributes and aliases. |

## Middleware Definitions

`MiddlewareFactory` resolves a definition into PSR-15 middleware:

```php
use Componenta\Http\Middleware\MiddlewareFactory;

$middleware = $factory->create(AuthMiddleware::class);
$middleware = $factory->create($callable);
$middleware = $factory->create('web');
```

Supported definitions:

- existing `MiddlewareInterface` instances
- PSR-11 service ids or class names
- callables adapted to middleware
- legacy request handler callables
- configured middleware group names

If no resolver can handle a definition, `MiddlewareResolutionException` is thrown with the original definition type.

## Resolver Chain

Resolution is delegated to `MiddlewareResolverInterface` implementations:

- `ClassNameResolver`
- `CallableResolver`
- `LegacyCallableResolver`
- `MiddlewareGroupResolver`
- `CompositeResolver`

`CompositeResolver` tries resolvers in order and returns the first successful result. This keeps custom resolution rules additive: register a resolver before or after the defaults depending on precedence.

## Groups

`MiddlewareGroup` is a named ordered collection:

```php
$group = new MiddlewareGroup('web', [
    SessionMiddleware::class,
    CsrfMiddleware::class,
]);

$pipeline = $factory->createGroup($group);
```

Groups are useful when route config should refer to a stable name like `web`, `api`, or `admin` without duplicating the middleware list.

## Config

`ConfigProvider` registers the factory and default resolvers. `ConfigKey` contains keys for middleware groups and resolver definitions.

Applications can replace or extend the resolver chain by changing DI config rather than changing route definitions.
