# Переход с версии 2.x на 3.x

- Обновите PHP до 8.5.13+, Config до 4.x, DI до 6.x и Pipeline до 1.1.1 или более новой версии в пределах 1.x.
- Методы `MiddlewareFactory::createMiddleware()`, `MiddlewareGroup::with()`, `withMany()` и `fromArray()` сохранены. Вместо удалённого публичного свойства `middlewares` используйте `toArray()` или итерацию.
- Удалены `MiddlewareFactoryAwareInterface` и `MiddlewareGroupResolver`. Группы разрешаются самой фабрикой; обратная ссылка резолверов на фабрику не используется.
- Метод `CompositeResolver::add()` заменён неизменяемой операцией `with()`. Используйте возвращённую цепочку при создании фабрики.
- Вместо `ClassNameResolver` используется `ServiceResolver`. Стандартная фабрика поддерживает произвольные идентификаторы сервисов контейнера.
- Ключ `ConfigKey::RESOLVERS` по-прежнему задаёт порядок пользовательских резолверов. Готовые объекты и группы имеют приоритет над ними.
- Под интерфейсом `MiddlewareResolverInterface` регистрируется цепочка расширений. Фабрика отдельно использует стандартное разрешение сервисов и вызываемых объектов.
- Для текущего HTTP-запроса используйте `#[Componenta\DI\Attribute\CurrentRequest]`. Неаннотированный тип запроса не означает контекст HTTP в DI 6.
- Вместо непосредственного конструктора `CallableAdapter` используйте `fromCallable()` с исполнителем `CallableExecutorInterface`. `LegacyCallableResolver`, `singlePassMiddleware()` и `doublePassMiddleware()` удалены из данного пакета. Если требуется совместимость с позиционным соглашением вызова, используйте отдельный пакет [componenta/middleware-legacy](https://github.com/componenta/middleware-legacy) с явными адаптерами либо подключаемыми резолверами сигнатур. Перепишите обработчики с позиционным соглашением вызова на реализацию `MiddlewareInterface` или вызываемый обработчик DI с `#[CurrentRequest]` и `RequestHandlerInterface`. При необходимости создавайте ответы явно: готовый ответ и вызываемое продолжение автоматически не передаются.
- Произвольные результаты вызова больше не сериализуются неявно. Чтобы сохранить такое поведение, настройте замыкание, возвращающее `ResponseInterface`. При использовании HTTP Responder можно передать `static fn (mixed $value): ResponseInterface => $responder->respond(content: $value)` и заменить фабрику `CallableResolver::class` в `Componenta\Config\ConfigKey::FACTORIES`. Зависимость от Responder принадлежит приложению.
- Свойство `MiddlewareResolutionExceptionInterface::$middleware` заменено на `$definitionType`. Обновите пользовательские реализации исключений. Исходная причина по-прежнему доступна через `getPrevious()`.
- Семантика атрибута продолжения адаптированных обработчиков запросов сохранена.

Потребителям необходимо обновить ограничения Composer и проверить используемые вызываемые обработчики и пользовательские резолверы.
