<?php declare(strict_types=1);

namespace CPSIT\Shortnr\Tests\Unit\Middleware;

use CPSIT\Shortnr\Config\ConfigLoader;
use CPSIT\Shortnr\Config\DTO\Config;
use CPSIT\Shortnr\Middleware\ShortNumberMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;

class ShortNumberMiddlewareTest extends TestCase
{
    private ConfigLoader $configLoader;
    private ShortNumberMiddleware $middleware;
    private RequestHandlerInterface $handler;
    private ResponseInterface $handlerResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configLoader = $this->createMock(ConfigLoader::class);
        $this->middleware = new ShortNumberMiddleware($this->configLoader);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->handlerResponse = $this->createMock(ResponseInterface::class);
    }

    /**
     * @dataProvider requestScenariosProvider
     */
    public function testMiddlewareProcessingBehavior(
        string $requestPath,
        array $configData,
        bool $expectsHandlerCall,
        string $expectedBehavior
    ): void {
        // Arrange
        $request = $this->createRequestMock($requestPath);
        $config = new Config($configData);
        $this->configLoader->method('getConfig')->willReturn($config);

        if ($expectsHandlerCall) {
            $this->handler
                ->expects($this->once())
                ->method('handle')
                ->with($request)
                ->willReturn($this->handlerResponse);
        } else {
            $this->handler->expects($this->never())->method('handle');
        }

        // Act
        $response = $this->middleware->process($request, $this->handler);

        // Assert
        switch ($expectedBehavior) {
            case 'passthrough':
                $this->assertSame($this->handlerResponse, $response);
                break;
            case 'redirect':
                $this->assertInstanceOf(RedirectResponse::class, $response);
                $this->assertSame(302, $response->getStatusCode());
                $this->assertStringContainsString('no-cache', $response->getHeaderLine('Cache-Control'));
                break;
        }
    }

    public static function requestScenariosProvider(): array
    {
        return [
            'Normal page request' => [
                'requestPath' => '/normal-page',
                'configData' => [],
                'expectsHandlerCall' => true,
                'expectedBehavior' => 'passthrough'
            ],
            'Root path request' => [
                'requestPath' => '/',
                'configData' => [],
                'expectsHandlerCall' => true,
                'expectedBehavior' => 'passthrough'
            ],
            'Long URL request' => [
                'requestPath' => '/very/long/path/to/some/resource',
                'configData' => [],
                'expectsHandlerCall' => true,
                'expectedBehavior' => 'passthrough'
            ],
            'URL with query parameters scenario' => [
                'requestPath' => '/page',
                'configData' => ['shortNr' => ['pages' => ['prefix' => 'p']]],
                'expectsHandlerCall' => true,
                'expectedBehavior' => 'passthrough'
            ],
            'Potential future short URL pattern' => [
                'requestPath' => '/p123',
                'configData' => ['shortNr' => ['pages' => ['prefix' => 'p']]],
                'expectsHandlerCall' => true,
                'expectedBehavior' => 'passthrough'
            ]
        ];
    }

    public function testMiddlewareCallsConfigLoaderForEveryRequest(): void
    {
        $request = $this->createRequestMock('/any-path');
        $config = new Config([]);

        $this->configLoader
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn($config);

        $this->handler->method('handle')->willReturn($this->handlerResponse);

        $this->middleware->process($request, $this->handler);
    }

    private function createRequestMock(string $path): ServerRequestInterface
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }
}
