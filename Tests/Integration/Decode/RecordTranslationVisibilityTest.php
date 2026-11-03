<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Decode;

use CPSIT\ShortNr\Tests\Integration\AbstractShortNrIntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Which record a plugin short number (REC<uid>-<lang>) resolves to.
 *
 * Contract:
 * - a translation counts only if it is visible to an anonymous visitor now:
 *   not hidden, starttime reached, endtime not passed (TCA enablecolumns)
 * - among several visible translations in one language the lowest uid wins (deterministic)
 * - no visible translation: fall back to the default language record (languageFallback, default true)
 * - the default language record itself not visible: notFound, whatever language was asked for
 *
 * Fixtures: Tests/Fixtures/Visibility/records.csv (plugin target page 3 "/records", EN page 4 "/records-en")
 */
final class RecordTranslationVisibilityTest extends AbstractShortNrIntegrationTestCase
{
    public static function fallbackEnabledProvider(): \Generator
    {
        yield 'default language' => ['REC200', '/records#200'];
        yield 'visible translation before a hidden one' => ['REC200-1', '/en/records-en#201'];
        yield 'hidden translation with the lower uid is skipped' => ['REC210-1', '/en/records-en#212'];
        yield 'translation with future starttime is skipped' => ['REC220-1', '/en/records-en#222'];
        yield 'translation with passed endtime is skipped' => ['REC230-1', '/en/records-en#232'];
        yield 'two visible translations: lowest uid wins' => ['REC240-1', '/en/records-en#241'];
        yield 'only a hidden translation: fall back to default language' => ['REC250-1', '/records#250'];
        yield 'short number with a translation uid resolves via its parent' => ['REC202-1', '/en/records-en#201'];
        yield 'all-languages record (-1) serves the asked language' => ['REC270-1', '/en/records-en#270'];
        yield 'hidden default record: notFound' => ['REC260', self::NOT_FOUND];
        yield 'hidden default record, visible translation: notFound' => ['REC260-1', self::NOT_FOUND];
        yield 'default record with future starttime: notFound' => ['REC280', self::NOT_FOUND];
        yield 'default record with passed endtime: notFound' => ['REC295', self::NOT_FOUND];
        yield 'unknown uid: notFound' => ['REC999', self::NOT_FOUND];
    }

    #[Test]
    #[DataProvider('fallbackEnabledProvider')]
    public function decodesToTheVisibleRecord(string $shortNr, string $expected): void
    {
        $this->configure(['record' => self::recordItem()]);

        self::assertSame($expected, $this->decode($shortNr));
    }

    public static function fallbackDisabledProvider(): \Generator
    {
        yield 'only a hidden translation: notFound' => ['REC250-1', self::NOT_FOUND];
        yield 'visible translation still resolves' => ['REC210-1', '/en/records-en#212'];
        yield 'default language unaffected' => ['REC250', '/records#250'];
    }

    #[Test]
    #[DataProvider('fallbackDisabledProvider')]
    public function languageFallbackFalseOnTheItemTurnsMissingTranslationsIntoNotFound(string $shortNr, string $expected): void
    {
        $this->configure(['record' => self::recordItem(['languageFallback' => false])]);

        self::assertSame($expected, $this->decode($shortNr));
    }

    #[Test]
    public function languageFallbackIsInheritedFromDefault(): void
    {
        $this->configure(['record' => self::recordItem()], ['languageFallback' => false]);

        self::assertSame(self::NOT_FOUND, $this->decode('REC250-1'));
    }

    #[Test]
    public function languageFallbackOnTheItemOverridesDefault(): void
    {
        $this->configure(['record' => self::recordItem(['languageFallback' => true])], ['languageFallback' => false]);

        self::assertSame('/records#250', $this->decode('REC250-1'));
    }
}
