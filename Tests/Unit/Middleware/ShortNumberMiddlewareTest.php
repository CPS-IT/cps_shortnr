<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Middleware;

use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Middleware\ShortNumberMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;

class ShortNumberMiddlewareTest extends TestCase
{
    private ConfigLoader $configLoader;
    private ShortNumberMiddleware $middleware;
    private ServerRequestInterface $request;
    private RequestHandlerInterface $handler;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configLoader = $this->createMock(ConfigLoader::class);
        $this->middleware = new ShortNumberMiddleware($this->configLoader);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    public function testConfigLoaderIsCalledDuringProcessing(): void
    {
        $this->configLoader
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $this->middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testMiddlewarePassesThroughWhenNotShortNrRequest(): void
    {
        $this->configLoader
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $this->middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testMiddlewareCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ShortNumberMiddleware::class, $this->middleware);
    }

    public function testConfigLoaderIsInjectedCorrectly(): void
    {
        $reflection = new \ReflectionClass($this->middleware);
        $property = $reflection->getProperty('configLoader');

        $this->assertSame($this->configLoader, $property->getValue($this->middleware));
    }
}
