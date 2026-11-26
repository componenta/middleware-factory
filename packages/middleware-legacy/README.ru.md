# Componenta Middleware Legacy

Отдельный необязательный пакет явной адаптации прежних соглашений вызова single-pass и double-pass из Componenta к PSR-15. Оба адаптера реализуют MiddlewareInterface и зависят только от контрактов PSR. Явные адаптеры не распознают сигнатуры, не ищут сервисы в контейнере и не нормализуют результаты. Опциональные резолверы интегрируются с Middleware Factory 3; сами адаптеры не зависят от пакетов Componenta.

## Установка

Требуется PHP 8.4 или 8.5. После публикации пакет устанавливается через Composer:

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

Обработчик получает запрос и вызываемое продолжение, принимающее запрос. Он может передать управление дальше, вернуть собственный ответ или изменить результат продолжения.

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

Второй аргумент конструктора — фабрика ответов PSR-17. Для каждого вызова запрашивается новый начальный ответ.

**Граница совместимости:** это прежнее соглашение Componenta, где продолжение принимает только запрос. Контракт PSR-15 RequestHandlerInterface не принимает начальный ответ. Вызов $next($request, $response) явно отклоняется с LogicException до передачи управления, чтобы изменения ответа не потерялись молча. Вместо этого изменяйте ответ после $next($request) либо возвращайте начальный ответ для завершения цепочки. Это не прозрачный адаптер для среды выполнения, передающей начальный ответ через всю цепочку double-pass.

## Контракты

Принимаются вызываемые объекты PHP: замыкания, публичные функции, пары публичных методов и вызываемые объекты. Ссылочные элементы массивов методов фиксируются до проверки; идентичность объектов сохраняется. Обработчик должен вернуть ResponseInterface; иной результат вызывает UnexpectedValueException. Исключения обработчика и продолжения распространяются без изменений. Дополнительные зависимости передавайте через замыкания или вызываемые объекты. Не сохраняйте продолжение после завершения обработки запроса.

## Интеграция

Адаптеры передаются непосредственно в Pipeline::pipe() или MiddlewareFactory::createMiddleware() как обычные объекты MiddlewareInterface. Эти пакеты не зависят от данного пакета.

```php
$pipeline = $pipeline->pipe($middleware);
$resolved = $factory->createMiddleware($middleware);
```

## Опциональные резолверы сигнатур

При использовании Componenta Middleware Factory 3 (PHP 8.5.13+) явно зарегистрируйте резолверы как сервисы и укажите их порядок в конфигурации. Основная фабрика не зависит от данного пакета. Адаптеры можно использовать и без фабрики.

```php
use Componenta\Config\ConfigKey as DIConfigKey;
use Componenta\Http\Middleware\ConfigKey;
use Componenta\Http\Middleware\Legacy\Resolver\SinglePassResolver;
use Componenta\Http\Middleware\Legacy\Resolver\DoublePassResolver;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

return [
    ConfigKey::RESOLVERS => [
        SinglePassResolver::class,
        DoublePassResolver::class,
    ],
    DIConfigKey::DEPENDENCIES => [
        DIConfigKey::FACTORIES => [
            SinglePassResolver::class => static fn (ContainerInterface $c) =>
                new SinglePassResolver($c),
            DoublePassResolver::class => static fn (ContainerInterface $c) =>
                new DoublePassResolver($c, $c->get(ResponseFactoryInterface::class)),
        ],
    ],
];
```

Фабрика ответов должна быть зарегистрирована приложением. Резолверы распознают ровно два или три параметра, передаваемых по значению и не являющихся вариативными: ServerRequestInterface, при необходимости ResponseInterface и callable либо Closure. Сигнатуры без типов, с объединениями, конкретными реализациями PSR и иные неоднозначные сигнатуры автоматически не распознаются; при необходимости используйте явный адаптер. Готовые объекты PSR-15 сохраняют приоритет. Зарегистрированный строковый идентификатор сервиса остаётся в ведении основной фабрики; для устаревшего обработчика, зарегистрированного как сервис, возвращайте соответствующий адаптер вместо исходного вызываемого объекта. Резолверы сигнатур не создают сервисы и не выполняют тело обработчика.

Для использования резолверов необходим интерфейс MiddlewareResolverInterface из основной фабрики. Эта зависимость указана как рекомендуемая в Composer; для использования только адаптеров устанавливать её не требуется. Интеграционные тесты используют разрабатываемые версии фабрики и DI до их публикации.

## Разработка

```sh
composer install
composer check
composer audit --locked --no-dev
```
