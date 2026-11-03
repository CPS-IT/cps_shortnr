<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Cache;

use CPSIT\ShortNr\Config\ConfigLoader;
use Throwable;

/**
 * Drops cached decode/encode results that read a table.
 *
 * DataHandler saves and Extbase persistence are covered automatically (ClearCacheDataHandlerHook,
 * ExtbasePersistenceListener). Code writing records with plain SQL (importers, CLI commands)
 * calls invalidateTable() for each table it changed, once at the end.
 */
class ShortNrCacheInvalidator
{
    /** @var array<string, true> */
    private array $deferred = [];
    private bool $shutdownRegistered = false;

    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly ConfigLoader $configLoader,
    ) {}

    /**
     * Only tables a config item reads are flushed (pages also flush the page tree: moves change site
     * roots). If the config cannot be read, everything is flushed rather than serving stale answers.
     *
     * @return bool false if nothing depends on the table
     */
    public function invalidateTable(string $table): bool
    {
        try {
            $relevant = $table === 'pages' || !empty($this->configLoader->getConfig()->getConfigItemsByTableName($table));
        } catch (Throwable) {
            $this->cacheManager->invalidateByTag(CacheTags::ALL);
            return true;
        }
        if (!$relevant) {
            return false;
        }

        $this->cacheManager->invalidateByTag(CacheTags::forTable($table));
        if ($table === 'pages') {
            $this->cacheManager->invalidateByTag(CacheTags::PAGE_TREE);
        }

        return true;
    }

    /**
     * Collects the table and flushes once at the end of the request/process: on FileBackend each
     * flush reads every cache file, an import of many records must not pay that per record.
     */
    public function invalidateTableDeferred(string $table): void
    {
        $this->deferred[$table] = true;
        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function($this->flushDeferred(...));
        }
    }

    /**
     * @internal called at shutdown; public for tests and for long running processes that want
     *           the flush earlier
     */
    public function flushDeferred(): void
    {
        $tables = array_keys($this->deferred);
        $this->deferred = [];
        foreach ($tables as $table) {
            try {
                $this->invalidateTable($table);
            } catch (Throwable) {
                // shutdown: nothing left to report to
            }
        }
    }
}
