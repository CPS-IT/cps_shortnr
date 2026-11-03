<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Config\DTO\Config;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Middleware\ShortNumberMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use TypedPatternEngine\Heuristic\HeuristicPatternInterface;
use TypedPatternEngine\TypedPatternEngine;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Runs short numbers through the real middleware, decoder, processors, repository and PageRouter
 * against an SQLite database. Only the config source, the cache and the site lookup are replaced:
 * - config: built in the test, so each test states exactly the config it relies on
 * - cache: pass-through, so every request hits the database (cache behaviour has its own tests)
 * - site: one site, root page 1, languages 0 (base "/") and 1 (base "/en/")
 *
 * Time is pinned to self::NOW, the same source the core enable-field restrictions read
 * ($GLOBALS['SIM_ACCESS_TIME'] and the Context "date" aspect), so fixtures can use fixed
 * FUTURE/PAST timestamps.
 */
abstract class AbstractShortNrIntegrationTestCase extends FunctionalTestCase
{
    protected const NOW = 1767225600;    // 2026-01-01
    protected const FUTURE = 1798761600; // 2027-01-01
    protected const PAST = 1735689600;   // 2025-01-01

    protected const NOT_FOUND = '/not-found';
    protected const PAGE_PATTERN = 'PAGE{uid:int(min=1)}(-{sys_language_uid:int(min=0,default=0)})';
    protected const RECORD_PATTERN = 'REC{uid:int(min=1)}(-{sys_language_uid:int(min=0,default=0)})';

    protected array $testExtensionsToLoad = [
        'cps_shortnr',
        __DIR__ . '/../Fixtures/Extensions/shortnr_test',
    ];

    private ?Config $config = null;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['EXEC_TIME'] = self::NOW;
        $GLOBALS['SIM_EXEC_TIME'] = self::NOW;
        $GLOBALS['ACCESS_TIME'] = self::NOW;
        $GLOBALS['SIM_ACCESS_TIME'] = self::NOW;
        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('@' . self::NOW)));

        $site = new Site('test', 1, [
            'base' => 'https://example.com/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'Deutsch',
                    'base' => '/',
                    'locale' => 'de_DE.UTF-8',
                ],
                [
                    'languageId' => 1,
                    'title' => 'English',
                    'base' => '/en/',
                    'locale' => 'en_US.UTF-8',
                    'fallbackType' => 'strict',
                ],
            ],
        ]);
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByRootPageId')->willReturn($site);
        $siteFinder->method('getSiteByPageId')->willReturn($site);
        $siteFinder->method('getSiteByIdentifier')->willReturn($site);
        $siteFinder->method('getAllSites')->willReturn(['test' => $site]);

        $cacheManager = $this->createStub(CacheManager::class);
        $cacheManager->method('getType3CacheValue')->willReturnCallback(
            static fn(string $cacheKey, callable $processBlock, ?int $ttl = null, array $tags = []): mixed => $processBlock()
        );

        $heuristic = $this->createStub(HeuristicPatternInterface::class);
        $heuristic->method('support')->willReturn(true);

        $configLoader = $this->createStub(ConfigLoader::class);
        $configLoader->method('getHeuristicPattern')->willReturn($heuristic);
        $configLoader->method('getConfig')->willReturnCallback(
            fn(): Config => $this->config ?? throw new \LogicException('call configure() in the test first')
        );

        $container = $this->getContainer();
        $container->set(ConfigLoader::class, $configLoader);
        $container->set(CacheManager::class, $cacheManager);
        $container->set(SiteFinder::class, $siteFinder);

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Visibility/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Visibility/records.csv');
    }

    /**
     * @param array<string, array> $items config items as in the YAML below "shortNr", without "_default"
     * @param array<string, mixed> $default overrides for "_default"
     */
    protected function configure(array $items, array $default = []): void
    {
        $default += [
            ConfigEnum::NotFound->value => '2',
            ConfigEnum::LanguageParentField->value => 'l10n_parent',
            ConfigEnum::LanguageField->value => 'sys_language_uid',
            ConfigEnum::IdentifierField->value => 'uid',
        ];

        $compiler = (new TypedPatternEngine())->getPatternCompiler();
        $compiled = [];
        foreach ($items as $name => $item) {
            $compiled[$name] = $compiler->compile($item[ConfigEnum::Pattern->value]);
        }

        $this->config = new Config([
            ConfigEnum::ENTRYPOINT->value => [ConfigEnum::DEFAULT_CONFIG->value => $default] + $items,
            ConfigEnum::Compiled->value => $compiled,
        ]);
    }

    protected static function pageItem(array $overrides = []): array
    {
        return $overrides + [
            ConfigEnum::Type->value => 'page',
            ConfigEnum::Table->value => 'pages',
            ConfigEnum::Pattern->value => self::PAGE_PATTERN,
        ];
    }

    protected static function recordItem(array $overrides = []): array
    {
        return $overrides + [
            ConfigEnum::Type->value => 'plugin',
            ConfigEnum::Table->value => 'tx_shortnrtest_record',
            ConfigEnum::Pattern->value => self::RECORD_PATTERN,
            ConfigEnum::Plugin->value => [
                'pid' => 3,
                'extension' => 'ShortnrTest',
                'plugin' => 'Record',
                'controller' => 'Record',
                'action' => 'show',
                'objectName' => 'record',
            ],
        ];
    }

    /**
     * Sends "https://example.com/<shortNr>" through the middleware.
     *
     * @return string|null the redirect target, reduced to "path" or "path#recordUid" for plugin
     *                     links so assertions show which row was picked; null if passed through
     */
    protected function decode(string $shortNr): ?string
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response(null, 418));

        $response = $this->get(ShortNumberMiddleware::class)->process(
            new ServerRequest(new Uri('https://example.com/' . $shortNr), 'GET'),
            $handler
        );
        if ($response->getStatusCode() === 418) {
            return null;
        }
        self::assertSame(301, $response->getStatusCode());

        return self::describeTarget($response->getHeaderLine('location'));
    }

    private static function describeTarget(string $location): string
    {
        $parts = parse_url($location);
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);
        $record = $query['tx_shortnrtest_record']['record'] ?? null;

        return $record === null ? $path : $path . '#' . $record;
    }
}
