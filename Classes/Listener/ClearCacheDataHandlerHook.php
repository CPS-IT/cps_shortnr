<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Listener;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Cache\ShortNrCacheInvalidator;
use Throwable;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Cache\Event\CacheFlushEvent;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsEventListener(
    identifier: 'shortnr/cache-event-listener',
    event: CacheFlushEvent::class,
    method: 'cacheFlush'
)]
class ClearCacheDataHandlerHook
{
    private ?CacheManager $cacheManager = null;

    /**
     * tables already flushed per DataHandler run; WeakMap, not spl_object_id(): ids of freed
     * objects are reused, a later DataHandler in the same process (imports, CLI) would skip its flush
     *
     * @var \WeakMap<DataHandler, array<string, true>>|null
     */
    private ?\WeakMap $flushedTables = null;

    /**
     * Called by DataHandler for every changed record (params table/uid, also with TSconfig
     * clearCache_disable) and for clear cache commands (param cacheCmd).
     */
    public function clearCache(array $params, DataHandler $dataHandler): void
    {
        if (!isset($params['cacheCmd']) && is_string($params['table'] ?? null)) {
            try {
                $this->invalidateTable($params['table'], $dataHandler);
            } catch (Throwable $e) {
                // never break the editor's save, never stay silent: stale answers live up to a week
                GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class)->error(
                    '[ShortNr] could not invalidate cached short numbers of table ' . $params['table'] . ': ' . $e->getMessage(),
                    ['exception' => $e::class]
                );
            }
            return;
        }

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

    /**
     * Decode and encode results that read this table: created, changed, hidden, deleted, moved or
     * translated records change them. Once per table and DataHandler run: on FileBackend every
     * flushByTag reads every cache file. Which tables count: ShortNrCacheInvalidator.
     */
    private function invalidateTable(string $table, DataHandler $dataHandler): void
    {
        $this->flushedTables ??= new \WeakMap();
        $flushed = $this->flushedTables[$dataHandler] ?? [];
        if (isset($flushed[$table])) {
            return;
        }
        $flushed[$table] = true;
        $this->flushedTables[$dataHandler] = $flushed;

        GeneralUtility::makeInstance(ShortNrCacheInvalidator::class)->invalidateTable($table);
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
