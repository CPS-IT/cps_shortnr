<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Fixtures\Extensions\shortnr_test\Classes;

use CPSIT\ShortNr\Event\ShortNrConfigPathEvent;

/**
 * Lets a test point the real ConfigLoader at a YAML file of its own.
 */
final class ConfigPathListener
{
    public static ?string $path = null;

    public function __invoke(ShortNrConfigPathEvent $event): void
    {
        if (self::$path !== null) {
            $event->addConfigPath(self::$path, 100);
        }
    }
}
