<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Decode;

use CPSIT\ShortNr\Tests\Integration\AbstractShortNrIntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Detail pages with speaking URLs: the path segment comes from a slug field of the record
 * (PersistedAliasMapper), as for news and most plugin records. Importers rename records with plain
 * SQL and nobody flushes caches for that: the short link must follow on the next request.
 *
 * Runs on the real cps_shortnr cache.
 */
final class RoutedRecordSlugTest extends AbstractShortNrIntegrationTestCase
{
    protected bool $stubCacheManager = false;

    protected function setUp(): void
    {
        parent::setUp();
        // the database is reset per test, the cache files in var/ are not
        $this->get(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('cps_shortnr')->flush();
        $this->configure(['record' => self::recordItem()]);
        $this->renameRecord(200, 'first-title');
    }

    protected function siteConfiguration(): array
    {
        return parent::siteConfiguration() + [
            'routeEnhancers' => [
                'Record' => [
                    'type' => 'Extbase',
                    'extension' => 'ShortnrTest',
                    'plugin' => 'Record',
                    'routes' => [
                        [
                            'routePath' => '/{record-slug}',
                            '_controller' => 'Record::show',
                            '_arguments' => ['record-slug' => 'record'],
                        ],
                    ],
                    'aspects' => [
                        'record-slug' => [
                            'type' => 'PersistedAliasMapper',
                            'tableName' => 'tx_shortnrtest_record',
                            'routeFieldName' => 'slug',
                        ],
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function speakingUrlOfTheRecordIsServed(): void
    {
        self::assertSame('/records/first-title', $this->decode('REC200'));
    }

    #[Test]
    public function recordRenamedThroughPlainSqlIsServedOnTheNextRequest(): void
    {
        self::assertSame('/records/first-title', $this->decode('REC200'));

        $this->renameRecord(200, 'renamed-title');

        self::assertSame('/records/renamed-title', $this->decode('REC200'));
    }

    private function renameRecord(int $uid, string $slug): void
    {
        $this->getConnectionPool()->getConnectionForTable('tx_shortnrtest_record')
            ->update('tx_shortnrtest_record', ['slug' => $slug], ['uid' => $uid]);
        // a new request starts with an empty runtime cache, only the cps_shortnr cache survives
        $this->get(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('runtime')->flush();
    }
}
