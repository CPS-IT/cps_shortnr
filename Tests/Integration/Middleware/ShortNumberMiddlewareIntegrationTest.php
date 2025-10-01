<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Middleware;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Config\DTO\Config;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Middleware\ShortNumberMiddleware;
use CPSIT\ShortNr\Service\DecoderService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TypedPatternEngine\Heuristic\HeuristicPatternInterface;
use TypedPatternEngine\TypedPatternEngine;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Integration test for ShortNumberMiddleware
 * Tests middleware from DI container with real DecoderService but mocked infrastructure
 */
class ShortNumberMiddlewareIntegrationTest extends FunctionalTestCase
{
    /**
     * Load our extension
     */
    protected array $testExtensionsToLoad = [
        'cps_shortnr',
    ];

    protected ShortNumberMiddleware $middleware;
    protected ConfigLoader|MockObject $configLoader;
    protected SiteFinder|MockObject $siteFinder;

    protected function setUp(): void
    {
        parent::setUp();

        $cacheManager = $this->createMock(CacheManager::class);
        $this->configLoader = $this->createMock(ConfigLoader::class);
        $this->siteFinder = $this->createMock(SiteFinder::class);

        // Override services in container with mocks
        $container = $this->getContainer();
        $container->set(ConfigLoader::class, $this->configLoader);
        $container->set(CacheManager::class, $cacheManager);
        $container->set(SiteFinder::class, $this->siteFinder);

        $this->siteFinder->expects($this->any())
            ->method('getSiteByRootPageId')
            ->willReturn(new Site('test', 1, []));

        $cacheManager->expects($this->any())
            ->method('getType3CacheValue')
            ->willReturnCallback(
                function (string $cacheKey, callable $processBlock, ?int $ttl = null, array $tags = []) {
                    return $processBlock();
                }
            );

        // Get middleware from DI container (with real DecoderService!)
        $this->middleware = $this->get(ShortNumberMiddleware::class);

        // Load a CSV file relative to test case file
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
    }

    /**
     * Data provider for middleware test scenarios
     */
    public static function middlewareTestCasesProvider(): \Generator
    {
        // Test case: /favicon.ico should pass through to handler
        yield 'favicon.ico passes through' => [
            'uri' => '/favicon.ico',
            'method' => 'GET',
            'expectsPassThrough' => true,
        ];

        yield 'PAGE1 request success' => [
            'uri' => '/PAGE1',
            'method' => 'GET',
            'expected' => '/sample-page-1-slug'
        ];
    }

    /**
     * Test middleware behavior with various requests
     * Middleware and DecoderService are retrieved from DI container
     */
    #[Test]
    #[DataProvider('middlewareTestCasesProvider')]
    public function middlewareHandlesRequestsCorrectly(
        string $uri,
        string $method,
        ?string $expected = null,
        bool $expectsPassThrough = false
    ): void {
        // Verify DecoderService is also real
        $decoderService = $this->get(DecoderService::class);
        $this->assertInstanceOf(DecoderService::class, $decoderService);

        // Create real TYPO3 request object
        $request = new ServerRequest(new Uri('https://example.com' . $uri), $method);

        // Create mock handler to verify it was called
        $handler = $this->createMock(RequestHandlerInterface::class);
        $expectedResponse = $this->createMock(ResponseInterface::class);

        if ($expectsPassThrough) {
            // Expect handler->handle() to be called exactly once
            $handler
                ->expects($this->once())
                ->method('handle')
                ->with($request)
                ->willReturn($expectedResponse);
        } else {
            // Expect handler->handle() to NOT be called
            $handler
                ->expects($this->never())
                ->method('handle');
        }

        $patternEngine = new TypedPatternEngine();
        $this->configLoader->expects($this->any())
            ->method('getConfig')
            ->willReturn(new Config(
                [
                    ConfigEnum::ENTRYPOINT->value => [
                        ConfigEnum::DEFAULT_CONFIG->value => [
                            ConfigEnum::NotFound->value => '/',
                            ConfigEnum::LanguageParentField->value => 'l10n_parent',
                            ConfigEnum::LanguageField->value => 'sys_language_uid',
                            ConfigEnum::IdentifierField->value => 'uid'
                        ],
                        'pages' => [
                            ConfigEnum::Type->value => 'page',
                            ConfigEnum::Table->value => 'pages',
                            ConfigEnum::Pattern->value => 'PAGE{uid:int(min=1)}(-{sys_language_uid:int(min=0,default=0)})'
                        ]
                    ],
                    ConfigEnum::Compiled->value => [
                        'pages' => $patternEngine->getPatternCompiler()->compile('PAGE{uid:int(min=1)}(-{sys_language_uid:int(min=0,default=0)})')
                    ]
                ]
                ));

        $heuristicMock = $this->createMock(HeuristicPatternInterface::class);
        $heuristicMock->expects($this->any())
            ->method('support')
            ->willReturn(true);

        $this->configLoader->expects($this->any())
            ->method('getHeuristicPattern')
            ->willReturn($heuristicMock);

        // Act: Process the request through middleware FROM CONTAINER
        $result = $this->middleware->process($request, $handler);

        // Assert: Verify the result
        if ($expectsPassThrough) {
            $this->assertSame($expectedResponse, $result, 'Middleware should pass through to handler');
        } else {
            $this->assertInstanceOf(RedirectResponse::class, $result);
            $this->assertSame($expected, $result->getHeader('location')[0] ?? null);
        }
    }
}
