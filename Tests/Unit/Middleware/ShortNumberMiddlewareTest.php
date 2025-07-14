<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Middleware;

use CPSIT\ShortNr\Middleware\ShortNumberMiddleware;
use CPSIT\ShortNr\Service\Url\DecoderService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;

class ShortNumberMiddlewareTest extends TestCase
{
    private DecoderService $decoderService;
    private ShortNumberMiddleware $middleware;
    private RequestHandlerInterface $handler;
    private ResponseInterface $handlerResponse;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->decoderService = $this->createMock(DecoderService::class);
        $this->middleware = new ShortNumberMiddleware($this->decoderService);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->handlerResponse = $this->createMock(ResponseInterface::class);
    }

    /**
     * @dataProvider requestScenariosProvider
     */
    public function testMiddlewareProcessingBehavior(
        string $requestPath,
        bool $isShortNrRequest,
        ?string $decodedUrl,
        bool $expectsHandlerCall,
        string $expectedBehavior
    ): void {
        // Arrange
        $request = $this->createRequestMock($requestPath);
        $this->decoderService->method('isShortNrRequest')->willReturn($isShortNrRequest);
        $this->decoderService->method('decodeRequest')->willReturn($decodedUrl);
        
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
                'isShortNrRequest' => false,
                'decodedUrl' => null,
                'expectsHandlerCall' => true,
                'expectedBehavior' => 'passthrough'
            ],
            'Root path request' => [
                'requestPath' => '/',
                'isShortNrRequest' => false,
                'decodedUrl' => null,
                'expectsHandlerCall' => true,
                'expectedBehavior' => 'passthrough'
            ],
            'Long URL request' => [
                'requestPath' => '/very/long/path/to/some/resource',
                'isShortNrRequest' => false,
                'decodedUrl' => null,
                'expectsHandlerCall' => true,
                'expectedBehavior' => 'passthrough'
            ],
            'Short URL request with decoded result' => [
                'requestPath' => '/p123',
                'isShortNrRequest' => true,
                'decodedUrl' => '/page-123',
                'expectsHandlerCall' => false,
                'expectedBehavior' => 'redirect'
            ],
            'Short URL request with null decode' => [
                'requestPath' => '/p999',
                'isShortNrRequest' => true,
                'decodedUrl' => null,
                'expectsHandlerCall' => false,
                'expectedBehavior' => 'redirect'
            ]
        ];
    }

    public function testMiddlewareCallsDecoderServiceForEveryRequest(): void
    {
        $request = $this->createRequestMock('/any-path');
        
        $this->decoderService
            ->expects($this->once())
            ->method('isShortNrRequest')
            ->with($request)
            ->willReturn(false);
        
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
