<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Cache;

use CPSIT\ShortNr\Service\EncoderService;
use CPSIT\ShortNr\Service\Url\Demand\Encode\ConfigNameEncoderDemand;
use CPSIT\ShortNr\Tests\Integration\AbstractShortNrIntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\ActionService;

/**
 * Decode and encode results are cached for a week. An editor's change must show up on the next
 * request anyway, and so must a record whose starttime/endtime passes, without anyone flushing caches.
 *
 * Runs on the real cps_shortnr cache (the extension's default configuration). Every test first puts
 * the old answer into the cache, then changes the data the way an editor does (DataHandler).
 */
final class CacheInvalidationTest extends AbstractShortNrIntegrationTestCase
{
    protected bool $stubCacheManager = false;

    // starttime of r281, endtime of r285
    private const IN_ONE_HOUR = self::NOW + 3600;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Visibility/be_users.csv');
        // the database is reset per test, the cache files in var/ are not
        $this->get(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('cps_shortnr')->flush();
        $this->configure([
            'pages' => self::pageItem(),
            'record' => self::recordItem(['condition' => ['record_type' => 0]]),
        ]);
    }

    #[Test]
    public function unhidingARecordIsServedOnTheNextRequest(): void
    {
        self::assertSame(self::NOT_FOUND, $this->decode('REC260'));

        $this->asEditor(fn(ActionService $editor) => $editor->modifyRecord('tx_shortnrtest_record', 260, ['hidden' => 0]));

        self::assertSame('/records#260', $this->decode('REC260'));
    }

    #[Test]
    public function everyDataHandlerRunInOneProcessFlushes(): void
    {
        // imports, scheduler tasks and CLI commands run several DataHandler instances in one process
        self::assertSame(self::NOT_FOUND, $this->decode('REC260'));
        $this->asEditor(fn(ActionService $editor) => $editor->modifyRecord('tx_shortnrtest_record', 260, ['hidden' => 0]));
        self::assertSame('/records#260', $this->decode('REC260'));

        $this->asEditor(fn(ActionService $editor) => $editor->modifyRecord('tx_shortnrtest_record', 260, ['hidden' => 1]));

        self::assertSame(self::NOT_FOUND, $this->decode('REC260'), 'second DataHandler run did not flush');
    }

    #[Test]
    public function creatingARecordOnAUidCachedAsNotFoundIsServed(): void
    {
        $nextUid = $this->maxUid('tx_shortnrtest_record') + 1;
        self::assertSame(self::NOT_FOUND, $this->decode('REC' . $nextUid));

        $this->asEditor(fn(ActionService $editor) => $editor->createNewRecord('tx_shortnrtest_record', 3, ['title' => 'new']));
        self::assertSame($nextUid, $this->maxUid('tx_shortnrtest_record'), 'fixture assumption: auto increment');

        self::assertSame('/records#' . $nextUid, $this->decode('REC' . $nextUid));
    }

    #[Test]
    public function deletingARecordIsNotServedAnymore(): void
    {
        self::assertSame('/records#200', $this->decode('REC200'));

        $this->asEditor(fn(ActionService $editor) => $editor->deleteRecord('tx_shortnrtest_record', 200));

        self::assertSame(self::NOT_FOUND, $this->decode('REC200'));
    }

    #[Test]
    public function newTranslationReplacesTheLanguageFallback(): void
    {
        self::assertSame('/records#300', $this->decode('REC300-1'));

        $translationUid = 0;
        $this->asEditor(function (ActionService $editor) use (&$translationUid): void {
            $translationUid = (int)$editor->localizeRecord('tx_shortnrtest_record', 300, 1)['tx_shortnrtest_record'][300];
        });

        // localize copies hidden=0 (no hideAtCopy in the TCA)
        self::assertSame('/en/records-en#' . $translationUid, $this->decode('REC300-1'));
    }

    #[Test]
    public function publishingAHiddenTranslationReplacesTheLanguageFallback(): void
    {
        self::assertSame('/records#250', $this->decode('REC250-1'));

        $this->asEditor(fn(ActionService $editor) => $editor->modifyRecord('tx_shortnrtest_record', 251, ['hidden' => 0]));

        self::assertSame('/en/records-en#251', $this->decode('REC250-1'));
    }

    #[Test]
    public function changingAConditionFieldIsServedOnTheNextRequest(): void
    {
        self::assertSame('/records#200', $this->decode('REC200'));

        $this->asEditor(fn(ActionService $editor) => $editor->modifyRecord('tx_shortnrtest_record', 200, ['record_type' => 1]));

        self::assertSame(self::NOT_FOUND, $this->decode('REC200'));
    }

    #[Test]
    public function hidingAPageIsServedOnTheNextRequest(): void
    {
        self::assertSame('/p20', $this->decode('PAGE20'));

        $this->asEditor(fn(ActionService $editor) => $editor->modifyRecord('pages', 20, ['hidden' => 1]));

        self::assertSame(self::NOT_FOUND, $this->decode('PAGE20'));
    }

    #[Test]
    public function scheduledRecordIsServedOnceItsStarttimeIsReached(): void
    {
        // embargo: a short link hit before the starttime must not stay dead for the cache lifetime
        // (1 hour: well inside the cache lifetime, so only a TTL bound to the starttime can help)
        self::assertSame(self::NOT_FOUND, $this->decode('REC281'));

        $this->travelTo(self::IN_ONE_HOUR + 1);

        self::assertSame('/records#281', $this->decode('REC281'));
    }

    #[Test]
    public function recordIsNotServedOnceItsEndtimeIsPassed(): void
    {
        self::assertSame('/records#285', $this->decode('REC285'));

        $this->travelTo(self::IN_ONE_HOUR + 1);

        self::assertSame(self::NOT_FOUND, $this->decode('REC285'));
    }

    #[Test]
    public function equivalentSpellingsShareOneCacheEntry(): void
    {
        // bots can produce unbounded spellings of one number (REC0200, REC00200, ...): each one a
        // separate cache file makes every flushByTag slower; they must share the entry of REC200.
        // Proven via a change the cache cannot know about (plain SQL, no DataHandler): the second
        // spelling must still get the cached answer of the first.
        self::assertSame('/records#200', $this->decode('REC200'));
        $this->getConnectionPool()->getConnectionForTable('tx_shortnrtest_record')
            ->update('tx_shortnrtest_record', ['hidden' => 1], ['uid' => 200]);

        self::assertSame('/records#200', $this->decode('REC0200'));
    }

    #[Test]
    public function changingAConditionFieldIsEncodedOnTheNextRequest(): void
    {
        self::assertSame('/REC200', $this->encode('record', 200));

        $this->asEditor(fn(ActionService $editor) => $editor->modifyRecord('tx_shortnrtest_record', 200, ['record_type' => 1]));

        self::assertNull($this->encode('record', 200), 'record no longer matches the config condition');
    }

    private function encode(string $configName, int $uid): ?string
    {
        // anonymous frontend request: a backend user would bypass the cache
        $GLOBALS['BE_USER'] = null;
        return $this->get(EncoderService::class)->encode((new ConfigNameEncoderDemand($configName, $uid))->setLanguageId(0));
    }

    /**
     * Runs DataHandler as a backend admin, then returns to an anonymous visitor.
     *
     * @param callable(ActionService): mixed $change
     */
    private function asEditor(callable $change): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $editor = new ActionService();
        $change($editor);
        self::assertSame([], $editor->getDataHandler()->errorLog, 'DataHandler refused the change');
        // free the DataHandler like a long running process eventually does (object ids get reused)
        unset($editor);
        gc_collect_cycles();
        unset($GLOBALS['BE_USER']);
        GeneralUtility::makeInstance(Context::class)->unsetAspect('backend.user');
    }

    private function travelTo(int $timestamp): void
    {
        $GLOBALS['EXEC_TIME'] = $GLOBALS['SIM_EXEC_TIME'] = $GLOBALS['ACCESS_TIME'] = $GLOBALS['SIM_ACCESS_TIME'] = $timestamp;
        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('@' . $timestamp)));
    }

    private function maxUid(string $table): int
    {
        $qb = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $qb->getRestrictions()->removeAll();
        return (int)$qb->selectLiteral('MAX(uid)')->from($table)->executeQuery()->fetchOne();
    }
}
