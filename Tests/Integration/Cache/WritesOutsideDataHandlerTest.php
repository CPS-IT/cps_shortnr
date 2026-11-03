<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Cache;

use CPSIT\ShortNr\Cache\ShortNrCacheInvalidator;
use CPSIT\ShortNr\Tests\Fixtures\Extensions\shortnr_test\Classes\Domain\Model\Record;
use CPSIT\ShortNr\Tests\Fixtures\Extensions\shortnr_test\Classes\Domain\Repository\RecordRepository;
use CPSIT\ShortNr\Tests\Integration\AbstractShortNrIntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Importers, scheduler tasks and frontend forms often write through Extbase or plain SQL, which
 * DataHandler's hook never sees.
 *
 * - Extbase writes are picked up automatically; the flush runs once per table at the end of the
 *   request/process (an import of 1000 records must not scan the cache 1000 times)
 * - plain SQL writers call ShortNrCacheInvalidator::invalidateTable()
 */
final class WritesOutsideDataHandlerTest extends AbstractShortNrIntegrationTestCase
{
    protected bool $stubCacheManager = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('cps_shortnr')->flush();
        $this->configure(['record' => self::recordItem()]);
    }

    #[Test]
    public function recordCreatedThroughExtbaseIsServedAfterTheRequest(): void
    {
        $record = new Record();
        $record->setTitle('imported');
        $this->get(RecordRepository::class)->add($record);
        // cached as notFound before the import persists
        $nextUid = $this->nextUid();
        self::assertSame(self::NOT_FOUND, $this->decode('REC' . $nextUid));

        $this->get(PersistenceManagerInterface::class)->persistAll();
        self::assertSame($nextUid, $record->getUid());
        $this->endOfRequest();

        self::assertSame('/records#' . $nextUid, $this->decode('REC' . $nextUid));
    }

    #[Test]
    public function recordUnhiddenThroughExtbaseIsServedAfterTheRequest(): void
    {
        self::assertSame(self::NOT_FOUND, $this->decode('REC260'));

        $repository = $this->get(RecordRepository::class);
        $record = $repository->findByUidIncludingHidden(260);
        self::assertNotNull($record);
        $record->setHidden(false);
        $repository->update($record);
        $this->get(PersistenceManagerInterface::class)->persistAll();
        $this->endOfRequest();

        self::assertSame('/records#260', $this->decode('REC260'));
    }

    #[Test]
    public function plainSqlWritersInvalidateExplicitly(): void
    {
        self::assertSame(self::NOT_FOUND, $this->decode('REC260'));
        $this->getConnectionPool()->getConnectionForTable('tx_shortnrtest_record')
            ->update('tx_shortnrtest_record', ['hidden' => 0], ['uid' => 260]);

        $this->get(ShortNrCacheInvalidator::class)->invalidateTable('tx_shortnrtest_record');

        self::assertSame('/records#260', $this->decode('REC260'));
    }

    private function endOfRequest(): void
    {
        // what the registered shutdown function does
        $this->get(ShortNrCacheInvalidator::class)->flushDeferred();
    }

    private function nextUid(): int
    {
        $qb = $this->getConnectionPool()->getQueryBuilderForTable('tx_shortnrtest_record');
        $qb->getRestrictions()->removeAll();
        return (int)$qb->selectLiteral('MAX(uid)')->from('tx_shortnrtest_record')->executeQuery()->fetchOne() + 1;
    }
}
