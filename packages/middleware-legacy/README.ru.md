# Componenta Middleware Legacy

Отдельный необязательный пакет явной адаптации прежних соглашений вызова single-pass и double-pass из Componenta к PSR-15. Оба адаптера реализуют MiddlewareInterface и зависят только от контрактов PSR. Автоматическое определение сигнатур, поиск сервисов в контейнере и нормализация результатов отсутствуют. Зависимостей от DI, Pipeline и Middleware Factory во время выполнения нет.

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

Адаптеры передаются непосредственно в Pipeline::pipe() или MiddlewareFactory::createMiddleware() как обычные объекты MiddlewareInterface. Эти пакеты не зависят от данного пакета и не распознают его специальным образом.

```php
$pipeline = $pipeline->pipe($middleware);
$resolved = $factory->createMiddleware($middleware);
```

## Разработка

```sh
composer install
composer check
composer audit --locked --no-dev
```
