<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Integration\Support;

use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Log\Writer\AbstractWriter;
use TYPO3\CMS\Core\Log\Writer\WriterInterface;

/**
 * Keeps every log record of the test in memory (static: TYPO3 instantiates writers itself).
 */
final class CapturingLogWriter extends AbstractWriter
{
    /** @var list<LogRecord> */
    public static array $records = [];

    public function writeLog(LogRecord $record): WriterInterface
    {
        self::$records[] = $record;
        return $this;
    }

    /**
     * @return list<LogRecord> records at warning level or worse
     */
    public static function problems(): array
    {
        return array_values(array_filter(
            self::$records,
            static fn(LogRecord $record): bool => \TYPO3\CMS\Core\Log\LogLevel::normalizeLevel($record->getLevel()) <= 4
        ));
    }
}
