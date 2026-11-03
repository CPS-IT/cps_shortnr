<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Cache;

use CPSIT\ShortNr\Cache\CacheAdapter\FastArrayFileCache;
use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Tests\Integration\AbstractShortNrIntegrationTestCase;
use CPSIT\ShortNr\Tests\Integration\Support\TagRecordingCacheManager;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\ActionService;

/**
 * A save flushes only what can depend on it: editors save content elements, file references,
 * categories ... all day, and on the default FileBackend every flushByTag reads every cache file.
 */
final class InvalidationScopeTest extends AbstractShortNrIntegrationTestCase
{
    private TagRecordingCacheManager $cacheManager;

    protected function createCacheManager(): CacheManager
    {
        return $this->cacheManager = new TagRecordingCacheManager($this->get(FastArrayFileCache::class));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Visibility/be_users.csv');
        $this->configure(['pages' => self::pageItem(), 'record' => self::recordItem()]);
    }

    #[Test]
    public function savingATableWithoutShortNumbersFlushesNothing(): void
    {
        $this->asEditor(fn(ActionService $editor) => $editor->createNewRecord('tt_content', 3, ['CType' => 'text', 'header' => 'x']));

        self::assertSame([], $this->cacheManager->flushedTags);
    }

    #[Test]
    public function savingAConfiguredTableFlushesItsTagOnce(): void
    {
        $this->asEditor(fn(ActionService $editor) => $editor->modifyRecords(3, [
            'tx_shortnrtest_record' => ['uid' => 200, 'title' => 'a'],
            'tt_content' => ['uid' => 0, 'header' => 'ignored'],
        ]));
        $this->asEditor(fn(ActionService $editor) => $editor->modifyRecord('tx_shortnrtest_record', 210, ['title' => 'b']));

        self::assertSame(['shortnr_table_tx_shortnrtest_record', 'shortnr_table_tx_shortnrtest_record'], $this->cacheManager->flushedTags, 'once per DataHandler run');
    }

    #[Test]
    public function savingAPageAlsoFlushesThePageTree(): void
    {
        $this->asEditor(fn(ActionService $editor) => $editor->modifyRecord('pages', 20, ['title' => 'x']));

        self::assertSame(['shortnr_table_pages', 'tree'], $this->cacheManager->flushedTags);
    }

    #[Test]
    public function unknownConfigFlushesEverythingRatherThanServingStaleAnswers(): void
    {
        $this->breakConfig(new ShortNrConfigException('broken'));

        $this->asEditor(fn(ActionService $editor) => $editor->createNewRecord('tt_content', 3, ['CType' => 'text', 'header' => 'x']));

        self::assertSame(['all'], $this->cacheManager->flushedTags);
    }

    /**
     * @param callable(ActionService): mixed $change
     */
    private function asEditor(callable $change): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $editor = new ActionService();
        $change($editor);
        self::assertSame([], $editor->getDataHandler()->errorLog, 'DataHandler refused the change');
        unset($GLOBALS['BE_USER']);
        GeneralUtility::makeInstance(Context::class)->unsetAspect('backend.user');
    }
}
