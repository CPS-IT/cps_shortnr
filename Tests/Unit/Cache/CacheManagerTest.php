<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Cache;

use CPSIT\ShortNr\Cache\CacheAdapter\FastArrayFileCache;
use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ExtensionSetup;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheManager as Typo3CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CacheManagerTest extends TestCase
{
    private CacheManager $cacheManager;
    private FastArrayFileCache $fastArrayFileCache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fastArrayFileCache = $this->createMock(FastArrayFileCache::class);
        $this->cacheManager = new CacheManager($this->fastArrayFileCache);
    }

    public static function arrayFileCacheDataProvider(): array
    {
        return [
            'returns injected FastArrayFileCache instance' => [
                'expectedInstance' => true
            ]
        ];
    }

    /**
     * @dataProvider arrayFileCacheDataProvider
     */
    public function testGetArrayFileCacheReturnsInjectedInstance(bool $expectedInstance): void
    {
        $result = $this->cacheManager->getArrayFileCache();
        
        if ($expectedInstance) {
            $this->assertSame($this->fastArrayFileCache, $result);
        } else {
            $this->assertNull($result);
        }
    }

    public static function typo3CacheDataProvider(): array
    {
        return [
            'TYPO3 cache manager available and working' => [
                'cacheManagerAvailable' => true,
                'cacheExists' => true,
                'throwException' => false,
                'expectedResult' => 'cache_instance'
            ],
            'TYPO3 cache manager throws exception' => [
                'cacheManagerAvailable' => true,
                'cacheExists' => false,
                'throwException' => true,
                'expectedResult' => null
            ],
            'TYPO3 cache manager not available' => [
                'cacheManagerAvailable' => false,
                'cacheExists' => false,
                'throwException' => false,
                'expectedResult' => null
            ]
        ];
    }

    /**
     * @dataProvider typo3CacheDataProvider
     */
    public function testGetCacheHandlesDifferentTypo3CacheScenarios(
        bool $cacheManagerAvailable,
        bool $cacheExists,
        bool $throwException,
        ?string $expectedResult
    ): void {
        $cacheManager = new CacheManager($this->fastArrayFileCache);
        
        if ($cacheManagerAvailable) {
            $typo3CacheManager = $this->createMock(Typo3CacheManager::class);
            
            if ($throwException) {
                $typo3CacheManager->method('getCache')
                    ->with(ExtensionSetup::CACHE_KEY)
                    ->willThrowException(new \Exception('Cache not found'));
            } elseif ($cacheExists) {
                $cacheFrontend = $this->createMock(FrontendInterface::class);
                $typo3CacheManager->method('getCache')
                    ->with(ExtensionSetup::CACHE_KEY)
                    ->willReturn($cacheFrontend);
                $expectedResult = $cacheFrontend;
            }
            
            GeneralUtility::setSingletonInstance(Typo3CacheManager::class, $typo3CacheManager);
        }
        
        $reflection = new \ReflectionClass($cacheManager);
        $method = $reflection->getMethod('getCache');

        $result = $method->invoke($cacheManager);
        
        if ($expectedResult === 'cache_instance') {
            $this->assertInstanceOf(FrontendInterface::class, $result);
        } else {
            $this->assertSame($expectedResult, $result);
        }
        
        if ($cacheManagerAvailable) {
            GeneralUtility::removeSingletonInstance(Typo3CacheManager::class, $typo3CacheManager);
        }
    }

    public function testGetCacheReturnsSameCacheInstanceOnSubsequentCalls(): void
    {
        $typo3CacheManager = $this->createMock(Typo3CacheManager::class);
        $cacheFrontend = $this->createMock(FrontendInterface::class);
        
        $typo3CacheManager->expects($this->once())
            ->method('getCache')
            ->with(ExtensionSetup::CACHE_KEY)
            ->willReturn($cacheFrontend);
        
        GeneralUtility::setSingletonInstance(Typo3CacheManager::class, $typo3CacheManager);
        
        $reflection = new \ReflectionClass($this->cacheManager);
        $method = $reflection->getMethod('getCache');

        $result1 = $method->invoke($this->cacheManager);
        $result2 = $method->invoke($this->cacheManager);
        
        $this->assertSame($cacheFrontend, $result1);
        $this->assertSame($result1, $result2);
        
        GeneralUtility::removeSingletonInstance(Typo3CacheManager::class, $typo3CacheManager);
    }


    public function testConstructorInitializesWithFastArrayFileCache(): void
    {
        $fastArrayFileCache = $this->createMock(FastArrayFileCache::class);
        $cacheManager = new CacheManager($fastArrayFileCache);
        
        $this->assertSame($fastArrayFileCache, $cacheManager->getArrayFileCache());
    }

    public static function cacheIntegrationDataProvider(): array
    {
        return [
            'integration with working cache system' => [
                'mockTypo3Cache' => true,
                'expectArrayCache' => true
            ],
            'integration with broken cache system' => [
                'mockTypo3Cache' => false,
                'expectArrayCache' => true
            ]
        ];
    }

    /**
     * @dataProvider cacheIntegrationDataProvider
     */
    public function testCacheManagerIntegration(bool $mockTypo3Cache, bool $expectArrayCache): void
    {
        $fastArrayFileCache = $this->createMock(FastArrayFileCache::class);
        $cacheManager = new CacheManager($fastArrayFileCache);
        
        if ($mockTypo3Cache) {
            $typo3CacheManager = $this->createMock(Typo3CacheManager::class);
            $cacheFrontend = $this->createMock(FrontendInterface::class);
            
            $typo3CacheManager->method('getCache')
                ->with(ExtensionSetup::CACHE_KEY)
                ->willReturn($cacheFrontend);
            
            GeneralUtility::setSingletonInstance(Typo3CacheManager::class, $typo3CacheManager);
        }
        
        $arrayCache = $cacheManager->getArrayFileCache();
        
        if ($expectArrayCache) {
            $this->assertSame($fastArrayFileCache, $arrayCache);
        } else {
            $this->assertNull($arrayCache);
        }
        
        if ($mockTypo3Cache) {
            GeneralUtility::removeSingletonInstance(Typo3CacheManager::class, $typo3CacheManager);
        }
    }

    public function testCacheManagerHandlesMultipleExceptionTypes(): void
    {
        $exceptionTypes = [
            new \Exception('Generic exception'),
            new \RuntimeException('Runtime exception'),
            new \InvalidArgumentException('Invalid argument'),
            new \LogicException('Logic exception')
        ];
        
        foreach ($exceptionTypes as $exception) {
            $typo3CacheManager = $this->createMock(Typo3CacheManager::class);
            $typo3CacheManager->method('getCache')
                ->with(ExtensionSetup::CACHE_KEY)
                ->willThrowException($exception);
            
            GeneralUtility::setSingletonInstance(Typo3CacheManager::class, $typo3CacheManager);
            
            $cacheManager = new CacheManager($this->fastArrayFileCache);
            $reflection = new \ReflectionClass($cacheManager);
            $method = $reflection->getMethod('getCache');

            $result = $method->invoke($cacheManager);
            
            $this->assertNull($result, sprintf('Exception type %s should result in null cache', get_class($exception)));
            
            GeneralUtility::removeSingletonInstance(Typo3CacheManager::class, $typo3CacheManager);
        }
    }
}
