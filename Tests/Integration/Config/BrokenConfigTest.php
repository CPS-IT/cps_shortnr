<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Config;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Config\ConfigLoader\YamlConfigSanitizer;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\PlatformAdapter\FileSystem\FileSystemInterface;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PathResolverInterface;
use CPSIT\ShortNr\Tests\Fixtures\Extensions\shortnr_test\Classes\ConfigPathListener;
use CPSIT\ShortNr\Tests\Integration\AbstractShortNrIntegrationTestCase;
use CPSIT\ShortNr\Tests\Integration\Support\CapturingLogWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A broken YAML (syntax error, invalid pattern) used to disable short numbers silently: the
 * middleware swallowed the exception on every request, and every request parsed the YAML again
 * because a failed build was never cached.
 *
 * Contract: the failure is remembered like a successful build (valid until a YAML file changes
 * or caches are flushed), logged once when it is detected, and short numbers pass through to
 * TYPO3 (404) instead of a 500.
 */
final class BrokenConfigTest extends AbstractShortNrIntegrationTestCase
{
    protected bool $stubConfigLoader = false;

    private string $yamlFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yamlFile = Environment::getVarPath() . '/shortnr-test-config.yaml';
        ConfigPathListener::$path = $this->yamlFile;
        $this->get(ConfigLoader::class)->clearCache();
    }

    protected function tearDown(): void
    {
        ConfigPathListener::$path = null;
        parent::tearDown();
    }

    public static function brokenYamlProvider(): \Generator
    {
        yield 'YAML syntax error' => ["shortNr:\n  pages:\n    type: page\n   pattern: [unclosed\n"];
        yield 'invalid pattern' => ["shortNr:\n  broken:\n    type: page\n    table: pages\n    pattern: \"X{uid:int(min=1)\"\n"];
    }

    #[Test]
    #[DataProvider('brokenYamlProvider')]
    public function brokenConfigIsLoggedOnceThenRememberedUntilTheYamlChanges(string $brokenYaml): void
    {
        file_put_contents($this->yamlFile, $brokenYaml);

        // request 1: detected, logged
        $this->assertConfigFails($this->freshLoader());
        self::assertCount(1, CapturingLogWriter::problems(), implode("\n", self::summarize(CapturingLogWriter::problems())));
        self::assertSame('error', CapturingLogWriter::problems()[0]->getLevel());
        self::assertStringContainsString('shortnr-test-config.yaml', CapturingLogWriter::problems()[0]->getMessage());

        // request 2: remembered, not parsed and not logged again
        $this->assertConfigFails($this->freshLoader());
        self::assertCount(1, CapturingLogWriter::problems(), 'logged again on the next request');

        // the editor fixes the YAML: picked up without a cache flush
        file_put_contents($this->yamlFile, "shortNr:\n  fixed:\n    type: page\n    table: pages\n    pattern: \"FIX{uid:int(min=1)}\"\n");
        touch($this->yamlFile, time() + 10);
        self::assertNotNull($this->freshLoader()->getConfig()->getConfigItem('fixed'));
    }

    #[Test]
    public function rememberedFailureIsRetriedAfterFiveMinutes(): void
    {
        // a failure can be transient (e.g. a ShortNrConfigLoadedEvent listener hitting a database
        // hiccup): it must heal without a cache flush, and a persistent one is reported again
        file_put_contents($this->yamlFile, "shortNr:\n  pages:\n    type: page\n   pattern: [unclosed\n");
        $this->assertConfigFails($this->freshLoader());

        $GLOBALS['EXEC_TIME'] = self::NOW + 299;
        $this->assertConfigFails($this->freshLoader());
        self::assertCount(1, CapturingLogWriter::problems(), 'retried before 5 minutes');

        $GLOBALS['EXEC_TIME'] = self::NOW + 301;
        $this->assertConfigFails($this->freshLoader());
        self::assertCount(2, CapturingLogWriter::problems(), 'not retried after 5 minutes');
    }

    #[Test]
    public function shortNumbersPassThroughWhileTheConfigIsBroken(): void
    {
        file_put_contents($this->yamlFile, "shortNr:\n  pages:\n    type: page\n   pattern: [unclosed\n");

        self::assertNull($this->decode('PAGE20'), 'expected pass-through to the TYPO3 stack');
        self::assertNull($this->decode('PAGE20'), 'second request');
        self::assertCount(1, CapturingLogWriter::problems(), implode("\n", self::summarize(CapturingLogWriter::problems())));
    }

    private function assertConfigFails(ConfigLoader $loader): void
    {
        try {
            $loader->getConfig();
            self::fail('broken config loaded');
        } catch (ShortNrConfigException) {
            // the documented exception type, not a raw parser/compiler exception
        }
    }

    /**
     * A new ConfigLoader has an empty runtime cache: same state as the next request.
     */
    private function freshLoader(): ConfigLoader
    {
        $loader = new ConfigLoader(
            $this->get(CacheManager::class),
            $this->get(FileSystemInterface::class),
            $this->get(PathResolverInterface::class),
            $this->get(YamlConfigSanitizer::class),
            $this->get(EventDispatcherInterface::class),
        );
        $loader->setLogger(GeneralUtility::makeInstance(LogManager::class)->getLogger(ConfigLoader::class));

        return $loader;
    }

    /**
     * @param list<LogRecord> $records
     * @return list<string>
     */
    private static function summarize(array $records): array
    {
        return array_map(static fn(LogRecord $r): string => $r->getLevel() . ': ' . $r->getMessage(), $records);
    }
}
