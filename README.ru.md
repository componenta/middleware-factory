# Componenta Middleware Factory

Преобразует доверенные определения промежуточных обработчиков в объекты PSR-15. Выполнение цепочек относится к ответственности [Componenta Pipeline](https://github.com/componenta/pipeline), а формирование и сериализация HTTP-ответов не входят в ответственность фабрики.

## Установка

```sh
composer require componenta/middleware-factory:^3.0
```


Требуются PHP 8.5.13+, Config 4, DI 6 и Pipeline 1.1. Провайдер конфигурации регистрируется через `extra.componenta.config-providers`. Без плагина Composer его можно явно передать фабрике конфигурации: `ComponentaHttpMiddlewareConfigProvider`.

## Определения

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


Порядок разрешения:

1. Готовый промежуточный обработчик возвращается без изменений.
2. Обработчик запроса адаптируется к промежуточному обработчику.
3. Элементы группы рекурсивно разрешаются в порядке регистрации.
4. Пользовательские резолверы вызываются в порядке конфигурации. Используется первый результат, отличный от `null`.
5. Строковый идентификатор проверяется в контейнере PSR-11. Зарегистрированный сервис должен предоставить промежуточный обработчик, обработчик запроса, группу или вызываемый объект PHP. Ошибка существующего сервиса не допускает перехода к другой интерпретации идентификатора.
6. Оставшиеся определения вызываемых объектов разрешаются средствами DI согласно его публичному контракту.

Группа является непустой упорядоченной коллекцией, а не именованным объектом. Для ссылки по имени зарегистрируйте группу как сервис. Ключи входного массива или итерируемой коллекции игнорируются; значения сохраняют порядок. Методы `with()` и `withMany()` создают новые группы, не изменяя исходную.

## Вызываемые обработчики

Стандартный вызываемый обработчик выполняется через DI. Текущий HTTP-запрос передаётся как контекст: используйте атрибут `#[CurrentRequest]` для его получения, другие атрибуты DI — для извлечения заголовков, параметров и тела запроса. Продолжение передаётся по типу `RequestHandlerInterface`. Сам по себе тип запроса не означает текущий HTTP-запрос в DI 6.

Допустимые результаты:

- `ResponseInterface` — ответ возвращается без изменений;
- `MiddlewareInterface` — выполняется с текущим запросом и продолжением;
- `RequestHandlerInterface` — получает текущий запрос напрямую.

Другие результаты вызывают `UnexpectedValueException`. Если необходимо преобразование произвольных значений, явно передайте замыкание в `CallableResolver` или `CallableAdapter::fromCallable()`. Оно должно вернуть ответ PSR-7; его исключения распространяются без изменений. Неявного поиска сервиса формирования ответов нет.

Устаревшие соглашения вызова поддерживаются через явные адаптеры:

```php
use Componenta\Http\Middleware\Adapter\CallableAdapter;

$single = CallableAdapter::singlePassMiddleware($callback, $invoker);
$double = CallableAdapter::doublePassMiddleware($callback, $invoker, $responseFactory);
```


Первый вариант получает запрос и вызываемое продолжение, второй — запрос, новый ответ и продолжение, принимающее запрос. Передаются только позиционные аргументы устаревшего соглашения, поэтому эти адаптеры работают и с нативным исполнителем. Контекст HTTP-запроса для атрибутов DI не передаётся; для него используйте стандартный адаптер.

Адаптированный обработчик запроса обычно является финальным, но может явно передать управление дальше через атрибут запроса `RequestHandlerAdapter::FALLBACK_HANDLER_ATTRIBUTES_KEY`. Исходный запрос не изменяется. Не сохраняйте запрос и продолжение после обработки. При прямом вызове `handle()` атрибут не добавляется.

## Расширение разрешения

Реализуйте `ResolverMiddlewareResolverInterface`. Возвращайте `null` только для неподдерживаемых определений. При ошибке начатого разрешения выбрасывайте исключение. Зарегистрируйте резолвер в контейнере и укажите его идентификатор в конфигурации приложения:

```php
use Componenta\Http\Middleware\ConfigKey;

return [
    ConfigKey::RESOLVERS => [CustomMiddlewareResolver::class],
];
```


Цепочка расширений неизменяема. `CompositeResolver::with()` создаёт новую цепочку и не меняет ранее созданные фабрики. Пользовательские резолверы не вызываются для готовых PSR-15 объектов и групп.

Ошибки разрешения реализуют `ExceptionMiddlewareResolutionExceptionInterface`. Свойство `definitionType` содержит тип определения, но не само определение. Исходная причина доступна через `getPrevious()`. Не раскрывайте цепочку исключений клиенту HTTP.

Используйте только доверенные определения из кода или конфигурации приложения. Не передавайте данные HTTP-запросов в качестве идентификаторов сервисов или вызываемых объектов.

## Разработка

```sh
composer test
composer phpstan
composer benchmark
```


Порядок перехода с версии 2.x описан в [руководстве по миграции](MIGRATION.ru.md).
