<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Decode;

use CPSIT\ShortNr\Tests\Integration\AbstractShortNrIntegrationTestCase;
use CPSIT\ShortNr\Tests\Integration\Support\CapturingLogWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Log\LogRecord;

/**
 * Not found is the normal answer to a bot probing REC1..REC99999 or to an unpublished record: it must
 * not produce error entries (with a stack trace each). Broken configuration must.
 */
final class DecodeLoggingTest extends AbstractShortNrIntegrationTestCase
{
    public static function notFoundProvider(): \Generator
    {
        yield 'unknown record' => ['REC999'];
        yield 'hidden record' => ['REC260'];
        yield 'missing translation, languageFallback off' => ['REC250-1', false];
        yield 'unknown page' => ['PAGE999'];
        yield 'sysfolder' => ['PAGE90'];
    }

    #[Test]
    #[DataProvider('notFoundProvider')]
    public function notFoundIsNoProblemWorthLogging(string $shortNr, bool $languageFallback = true): void
    {
        $this->configure([
            'pages' => self::pageItem(),
            'record' => self::recordItem(['languageFallback' => $languageFallback]),
        ]);

        self::assertSame(self::NOT_FOUND, $this->decode($shortNr));
        self::assertSame([], self::summarize(CapturingLogWriter::problems()));
    }

    #[Test]
    public function emptyNotFoundPassesTheRequestOnToTypo3(): void
    {
        // notFound "" means: no redirect, let the TYPO3 stack answer (404 page), not a 500
        $this->configure(['record' => self::recordItem()], ['notFound' => '']);

        self::assertNull($this->decode('REC260'), 'hidden record');
        self::assertNull($this->decode('REC999'), 'unknown record');
        self::assertSame('/records#200', $this->decode('REC200'));
        self::assertSame([], self::summarize(CapturingLogWriter::problems()));
    }

    #[Test]
    public function brokenProcessorConfigIsLoggedAsErrorWithoutStackTrace(): void
    {
        $item = self::recordItem();
        unset($item['plugin']['pid']);
        $this->configure(['record' => $item]);

        self::assertSame(self::NOT_FOUND, $this->decode('REC200'));

        $problems = CapturingLogWriter::problems();
        self::assertCount(1, $problems, implode("\n", self::summarize($problems)));
        self::assertSame('error', $problems[0]->getLevel());
        self::assertStringContainsString('pid', $problems[0]->getMessage());
        self::assertSame('REC200', $problems[0]->getData()['shortNr'] ?? null);
        self::assertArrayNotHasKey('trace', $problems[0]->getData(), 'a trace per request floods the log');
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
