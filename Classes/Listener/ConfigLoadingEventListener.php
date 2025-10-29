<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Listener;

use CPSIT\ShortNr\Config\ExtensionSetup;
use CPSIT\ShortNr\Event\ShortNrConfigPathEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener(
    identifier: 'cps-shortnr/configLoadingListener',
    event: ShortNrConfigPathEvent::class,
    method: 'loadDefaultConfig'
)]
class ConfigLoadingEventListener
{
    public function loadDefaultConfig(ShortNrConfigPathEvent $event): void
    {
        $event->addConfigPath(
            'EXT:'. ExtensionSetup::EXT_KEY .'/Configuration/config.yaml',
            -1000
        );
    }
}
