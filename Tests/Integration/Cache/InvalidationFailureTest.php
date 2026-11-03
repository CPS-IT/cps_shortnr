<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Cache;

use CPSIT\ShortNr\Cache\CacheAdapter\FastArrayFileCache;
use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Tests\Integration\AbstractShortNrIntegrationTestCase;
use CPSIT\ShortNr\Tests\Integration\Support\CapturingLogWriter;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\ActionService;

/**
 * A failed invalidation leaves stale short number answers for up to a week: it must never break
 * the editor's save, and it must never be silent (seen live: a stale DI container after a code
 * switch without cache flush made every invalidation fail without a trace).
 */
final class InvalidationFailureTest extends AbstractShortNrIntegrationTestCase
{
    protected function createCacheManager(): CacheManager
    {
        return new class ($this->get(FastArrayFileCache::class)) extends CacheManager {
            public function invalidateByTag(string $tag): void
            {
                throw new \RuntimeException('cache backend unavailable');
            }
        };
    }

    #[Test]
    public function failedInvalidationIsLoggedAndTheSaveSucceeds(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Visibility/be_users.csv');
        $this->configure(['record' => self::recordItem()]);

        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $editor = new ActionService();
        $editor->modifyRecord('tx_shortnrtest_record', 200, ['title' => 'changed']);
        unset($GLOBALS['BE_USER']);
        GeneralUtility::makeInstance(Context::class)->unsetAspect('backend.user');

        self::assertSame([], $editor->getDataHandler()->errorLog, 'the save itself must succeed');
        $problems = CapturingLogWriter::problems();
        self::assertCount(1, $problems);
        self::assertSame('error', $problems[0]->getLevel());
        self::assertStringContainsString('tx_shortnrtest_record', $problems[0]->getMessage());
        self::assertStringContainsString('cache backend unavailable', $problems[0]->getMessage());
    }
}
