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

}
