<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Listener;

use CPSIT\ShortNr\Cache\CacheManager;
use Throwable;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Cache\Event\CacheFlushEvent;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsEventListener(
    identifier: 'shortnr/cache-event-listener',
    event: CacheFlushEvent::class,
    method: 'cacheFlush'
)]
class ClearCacheDataHandlerHook
{
    private ?CacheManager $cacheManager = null;

    public function clearCache(array $params, DataHandler $dataHandler): void
    {
        $eventType = $params['cacheCmd'] ?? null;
        try {
            match($eventType) {
                'all' => $this->cacheFlush(null),
                'pages' => $this->invalidatePageCaches(),
                null => null,
                default => is_numeric($eventType) ? (fn() => $this->invalidatePageCache((int)$eventType))() : null
            };
        } catch (Throwable) {}
    }

    public function cacheFlush(?CacheFlushEvent $event): void
    {
        $this->invalidateConfigCache();
        $this->invalidatePageCaches();
    }

    private function invalidateConfigCache(): void
    {
        $this->getCacheManager()->getArrayFileCache()->invalidateCacheDirectory();
    }


    private function invalidatePageCaches(): void
    {
        $this->getCacheManager()->invalidateByTag('all');
    }

    private function invalidatePageCache(int $pageId): void
    {
        if ($pageId <= 0) {
            return;
        }
        // for now all ... since we don't know what cache is on what page
        $this->getCacheManager()->invalidateByTag('all');
    }

    private function getCacheManager(): CacheManager
    {
        return $this->cacheManager ??= GeneralUtility::makeInstance(CacheManager::class);
    }
}
