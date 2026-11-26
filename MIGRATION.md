# Migrating from 2.x to 3.x

- Update PHP to 8.5.13+, Config to 4.x, DI to 6.x, and Pipeline to 1.1.1 or later within 1.x.
- `MiddlewareFactory::createMiddleware()`, `MiddlewareGroup::with()`, `withMany()` and `fromArray()` remain available. Use `toArray()` or iteration rather than the removed public `middlewares` property.
- `MiddlewareFactoryAwareInterface` and `MiddlewareGroupResolver` are removed. Group resolution belongs to the factory; extension resolvers no longer receive a factory back-reference.
- `CompositeResolver::add()` is replaced by immutable `with()`. Use the returned chain when constructing a factory. Do not mutate a registered chain.
- `ClassNameResolver` is replaced by `ServiceResolver`. The default factory recognizes arbitrary container identifiers, not only implementation class names.
- `ConfigKey::RESOLVERS` remains the ordered list of extension resolver service ids. Standard object and group definitions have precedence over extensions.
- The resolver registered as `MiddlewareResolverInterface` is the extension chain. The factory separately supplies built-in service and callable resolution.
- Use `#[Componenta\DI\Attribute\CurrentRequest]` for the current request. An unannotated request type is not the HTTP context in DI 6.
- `CallableAdapter::fromCallable()` replaces direct construction and requires `CallableExecutorInterface`. `LegacyCallableResolver`, `singlePassMiddleware()` and `doublePassMiddleware()` are removed from this package. If positional compatibility is necessary, use the separate [componenta/middleware-legacy](https://github.com/componenta/middleware-legacy) package with explicit adapters or opt-in signature resolvers. Rewrite positional middleware as a PSR-15 `MiddlewareInterface` implementation or a DI callable using `#[CurrentRequest]` and `RequestHandlerInterface`. Create responses explicitly when needed; no response or callable continuation is automatically supplied.
- Arbitrary callable return values are no longer serialized implicitly. To retain this behavior, configure a normalization closure returning `ResponseInterface`. Applications using HTTP Responder can opt in with `static fn (mixed $value): ResponseInterface => $responder->respond(content: $value)` and replace the `CallableResolver::class` factory in `Componenta\Config\ConfigKey::FACTORIES`. The application owns the Responder dependency.
- `MiddlewareResolutionExceptionInterface::$middleware` is replaced with `$definitionType`. Update custom exception implementations. The underlying cause is still exposed via `getPrevious()`.
- Request handler continuation attributes retain their existing semantics.

Consumers must update Composer constraints to allow 3.x and verify their callback and custom resolver contracts.
