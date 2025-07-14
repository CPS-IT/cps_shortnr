<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Cache;

use CPSIT\ShortNr\Cache\CacheAdapter\FastArrayFileCache;
use CPSIT\ShortNr\Config\ExtensionSetup;
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

    /**
     * @return FastArrayFileCache|null
     */
    public function getArrayFileCache(): ?FastArrayFileCache
    {
        return $this->arrayFileCache;
    }
}
