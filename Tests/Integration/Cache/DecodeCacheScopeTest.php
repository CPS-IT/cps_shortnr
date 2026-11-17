<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Cache;

use CPSIT\ShortNr\Cache\CacheAdapter\FastArrayFileCache;
use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Tests\Integration\AbstractShortNrIntegrationTestCase;
use CPSIT\ShortNr\Tests\Integration\Support\TagRecordingCacheManager;
use PHPUnit\Framework\Attributes\Test;

/**
 * What the decoder writes to the cache.
 *
 * Found answers are not cached: the redirect target is built from the slug of the record, of the
 * plugin page and its parents, from the site configuration and from visibility; importers, plain
 * SQL and upgrade wizards change all of these without any hook seeing it. Resolving a found answer
 * costs the same queries a correct cache check would need.
 *
 * notFound answers are cached (bots probe number ranges), for an hour at most.
 */
final class DecodeCacheScopeTest extends AbstractShortNrIntegrationTestCase
{
    private TagRecordingCacheManager $cacheManager;

    protected function createCacheManager(): CacheManager
    {
        return $this->cacheManager = new TagRecordingCacheManager($this->get(FastArrayFileCache::class));
    }

    protected function setUp(): void
    {
        parent::setUp();
        // the database is reset per test, the cache files in var/ are not
        $this->get(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('cps_shortnr')->flush();
        $this->configure(['record' => self::recordItem()]);
    }

    #[Test]
    public function foundAnswerIsNotWritten(): void
    {
        self::assertSame('/records#200', $this->decode('REC200'));
        self::assertSame('/records#200', $this->decode('REC200'));

        self::assertSame([], $this->cacheManager->written);
    }

    #[Test]
    public function notFoundAnswerIsWrittenOnceAndServedFromTheCache(): void
    {
        self::assertSame(self::NOT_FOUND, $this->decode('REC260'));
        self::assertSame(self::NOT_FOUND, $this->decode('REC260'));
        self::assertSame(self::NOT_FOUND, $this->decode('REC0260'));

        self::assertCount(1, $this->cacheManager->written, 'second and third request must be cache hits');
        $entry = $this->cacheManager->written[0];
        self::assertLessThanOrEqual(3600, $entry['ttl']);
        // dropped when an editor (or Extbase, or invalidateTable()) changes a record of the table
        self::assertContains('shortnr_table_tx_shortnrtest_record', $entry['tags']);
    }
}
