<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url;

use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Service\DecoderService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class DecoderServiceTest extends TestCase
{
    private ConfigLoader $configLoader;
    private DecoderService $decoderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configLoader = $this->createMock(ConfigLoader::class);
        $this->decoderService = new DecoderService($this->configLoader);
    }

    /**
     * @dataProvider decodeRequestScenariosProvider
     */
    public function testDecodeRequestReturnsPlaceholderResult(
        string $requestPath,
        ?string $expectedResult
    ): void {
        // Arrange
        $request = $this->createRequestMock($requestPath);

        // Act
        $result = $this->decoderService->decodeRequest($request);

        // Assert - Currently returns null as placeholder
        $this->assertSame($expectedResult, $result);
    }

    /**
     * @dataProvider decodeScenariosProvider
     */
    public function testDecodeReturnsPlaceholderResult(
        string $uri,
        ?string $expectedResult
    ): void {
        // Act
        $result = $this->decoderService->decode($uri);

        // Assert - Currently returns null as placeholder
        $this->assertSame($expectedResult, $result);
    }

    public static function decodeRequestScenariosProvider(): array
    {
        return [
            'Normal page request' => [
                'requestPath' => '/normal-page',
                'expectedResult' => null
            ],
            'Short URL pattern' => [
                'requestPath' => '/p123',
                'expectedResult' => null
            ],
            'Root path' => [
                'requestPath' => '/',
                'expectedResult' => null
            ],
            'Empty path' => [
                'requestPath' => '',
                'expectedResult' => null
            ]
        ];
    }

    public static function decodeScenariosProvider(): array
    {
        return [
            'Normal page URI' => [
                'uri' => '/normal-page',
                'expectedResult' => null
            ],
            'Short URL pattern URI' => [
                'uri' => '/p123',
                'expectedResult' => null
            ],
            'Root URI' => [
                'uri' => '/',
                'expectedResult' => null
            ],
            'Empty URI' => [
                'uri' => '',
                'expectedResult' => null
            ]
        ];
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
