<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Cache;

use CPSIT\ShortNr\Cache\CacheAdapter\FastArrayFileCache;
use CPSIT\ShortNr\Config\ExtensionSetup;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Cache\CacheManager as Typo3CacheManager;
use Throwable;

class CacheManager
{
    private readonly ?FrontendInterface $cache;

    public function __construct(
        private readonly FastArrayFileCache $arrayFileCache
    )
    {}

    /**
     * @return FastArrayFileCache|null
     */
    public function getArrayFileCache(): ?FastArrayFileCache
    {
        return $this->arrayFileCache;
    }

    /**
     * @param string $cacheKey
     * @param callable $processBlock must return a string
     * @param int|null $ttl Lifetime of this cache entry in seconds. If NULL is specified, the default lifetime is used. "0" means unlimited lifetime.
     * @return string|null
     * @throws ShortNrCacheException
     */
    public function getType3CacheValue(string $cacheKey, callable $processBlock, ?int $ttl = null): ?string
    {
        $cleanCacheKey = md5($cacheKey);
        $cache = $this->getCache();
        $cacheValue = $cache?->get($cleanCacheKey);

        if ($cacheValue === null || $cacheValue === false) {
            $value = $processBlock();
            if (!is_string($value)) {
                throw new ShortNrCacheException('invalid cache value, expected string');
            }
            $cache?->set($cleanCacheKey, $value, lifetime:  $ttl);
            return $value;
        }

        if (is_string($cacheValue)) {
            return $cacheValue;
        }

        return null;
    }

    /**
     * @return FrontendInterface|null
     */
    protected function getCache(): ?FrontendInterface
    {
        try {
            /** @var Typo3CacheManager $tcm */
            $tcm = GeneralUtility::makeInstance(Typo3CacheManager::class);
            return $this->cache ??= $tcm->getCache(ExtensionSetup::CACHE_KEY);
        } catch (Throwable) {
            return $this->cache = null;
        }
    }
}
