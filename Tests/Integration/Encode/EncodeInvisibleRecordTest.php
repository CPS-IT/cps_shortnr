<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Encode;

use CPSIT\ShortNr\Service\EncoderService;
use CPSIT\ShortNr\Service\Url\Demand\Encode\ConfigNameEncoderDemand;
use CPSIT\ShortNr\Tests\Integration\AbstractShortNrIntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Encoding must keep working for records an anonymous visitor cannot see (yet): editors preview
 * hidden or scheduled records and copy their short link before publishing (commit 4e96b17).
 * Visibility is a decode concern only.
 */
final class EncodeInvisibleRecordTest extends AbstractShortNrIntegrationTestCase
{
    #[Test]
    public function hiddenRecordWhoseConditionFieldMustBeLoadedStillEncodes(): void
    {
        $GLOBALS['BE_USER'] = null;
        // record_type is not part of the pattern, so the encoder has to load it from the hidden row 290
        $this->configure(['record' => self::recordItem(['condition' => ['record_type' => 1]])]);

        $shortNr = $this->get(EncoderService::class)->encode(
            (new ConfigNameEncoderDemand('record', 290))->setLanguageId(0)
        );

        self::assertSame('/REC290', $shortNr);
    }

    #[Test]
    public function hiddenPageStillEncodes(): void
    {
        $GLOBALS['BE_USER'] = null;
        $this->configure(['pages' => self::pageItem()]);

        $shortNr = $this->get(EncoderService::class)->encode(
            (new ConfigNameEncoderDemand('pages', 70))->setLanguageId(0)
        );

        self::assertSame('/PAGE70', $shortNr);
    }

    #[Test]
    public function encodesWithoutBackendUserGlobal(): void
    {
        // CLI, scheduler tasks and early middlewares run without $GLOBALS['BE_USER']
        unset($GLOBALS['BE_USER']);
        $this->configure(['record' => self::recordItem()]);

        $shortNr = $this->get(EncoderService::class)->encode(
            (new ConfigNameEncoderDemand('record', 200))->setLanguageId(0)
        );

        self::assertSame('/REC200', $shortNr);
    }
}
