<?php
declare(strict_types=1);
namespace Componenta\Http\Middleware\Tests;
use Componenta\Config\ConfigFactory;
use Componenta\Config\ConfigKey;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\ConfigProvider;
use Componenta\Http\Middleware\MiddlewareFactory;
use Componenta\Http\Responder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareCalls { public int $value = 0; }
final readonly class ContainerMiddleware implements MiddlewareInterface {
    public function __construct(private MiddlewareCalls $calls) {}
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        ++$this->calls->value;
        return new Response(202);
    }
}
final class ContainerIntegrationTest extends TestCase {
    public function testConfiguredFactoryResolvesMiddlewareUsingTheExistingContainer(): void {
        $calls = new MiddlewareCalls();
        $psr17 = new Psr17Factory();
        $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider(),
            static fn (): array => [ConfigKey::DEPENDENCIES => [ConfigKey::SERVICES => [
                MiddlewareCalls::class => $calls, Responder::class => new Responder($psr17, $psr17),
            ]]]);
        $container = (new ContainerFactory())->create($composition->config, $composition->dependencies);
        $middleware = $container->get(MiddlewareFactory::class)->createMiddleware(ContainerMiddleware::class);
        $terminal = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface { return new Response(404); }
        };
        self::assertSame(202, $middleware->process(new ServerRequest('GET', '/'), $terminal)->getStatusCode());
        self::assertSame(1, $calls->value);
    }
}
