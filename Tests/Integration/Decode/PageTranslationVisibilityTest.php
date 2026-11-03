<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Decode;

use CPSIT\ShortNr\Tests\Integration\AbstractShortNrIntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Which page a page short number (PAGE<uid>-<lang>) resolves to.
 *
 * Same visibility contract as records (see RecordTranslationVisibilityTest), plus:
 * pages that cannot be rendered (sysfolder 254, spacer 199) are never a target.
 *
 * Fixtures: Tests/Fixtures/Visibility/pages.csv
 */
final class PageTranslationVisibilityTest extends AbstractShortNrIntegrationTestCase
{
    public static function fallbackEnabledProvider(): \Generator
    {
        yield 'default language' => ['PAGE20', '/p20'];
        yield 'explicit default language' => ['PAGE20-0', '/p20'];
        yield 'visible translation before a hidden one' => ['PAGE20-1', '/en/p20-visible'];
        yield 'hidden translation with the lower uid is skipped' => ['PAGE30-1', '/en/p30-visible'];
        yield 'translation with future starttime is skipped' => ['PAGE40-1', '/en/p40-visible'];
        yield 'translation with passed endtime is skipped' => ['PAGE50-1', '/en/p50-visible'];
        yield 'only a hidden translation: fall back to default language' => ['PAGE60-1', '/p60'];
        yield 'no translation at all: fall back to default language' => ['PAGE100-1', '/p100'];
        yield 'short number with a translation uid resolves via its parent' => ['PAGE31-1', '/en/p30-visible'];
        yield 'hidden default page: notFound' => ['PAGE70', self::NOT_FOUND];
        yield 'hidden default page, visible translation: notFound' => ['PAGE70-1', self::NOT_FOUND];
        yield 'default page with future starttime: notFound' => ['PAGE80', self::NOT_FOUND];
        yield 'default page with passed endtime: notFound' => ['PAGE110', self::NOT_FOUND];
        yield 'sysfolder: notFound' => ['PAGE90', self::NOT_FOUND];
        yield 'spacer: notFound' => ['PAGE95', self::NOT_FOUND];
        yield 'unknown uid: notFound' => ['PAGE999', self::NOT_FOUND];
    }

    #[Test]
    #[DataProvider('fallbackEnabledProvider')]
    public function decodesToTheVisiblePage(string $shortNr, string $expected): void
    {
        $this->configure(['pages' => self::pageItem()]);

        self::assertSame($expected, $this->decode($shortNr));
    }

    public static function fallbackDisabledProvider(): \Generator
    {
        yield 'only a hidden translation: notFound' => ['PAGE60-1', self::NOT_FOUND];
        yield 'no translation at all: notFound' => ['PAGE100-1', self::NOT_FOUND];
        yield 'visible translation still resolves' => ['PAGE30-1', '/en/p30-visible'];
        yield 'default language unaffected' => ['PAGE60', '/p60'];
    }

    #[Test]
    #[DataProvider('fallbackDisabledProvider')]
    public function languageFallbackFalseTurnsMissingTranslationsIntoNotFound(string $shortNr, string $expected): void
    {
        $this->configure(['pages' => self::pageItem(['languageFallback' => false])]);

        self::assertSame($expected, $this->decode($shortNr));
    }
}
