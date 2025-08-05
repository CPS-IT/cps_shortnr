<?php declare(strict_types=1);

namespace CPSIT\ShortNr\EventListener;

use CPSIT\ShortNr\Config\ConfigLoader;
use TYPO3\CMS\Core\Cache\Event\CacheFlushEvent;

class CacheEventListener
{
    public function __construct(
        private readonly ConfigLoader $configLoader,
    )
    {}

    /**
     * @param object $event
     * @return void
     */
    public function handleEvents(object $event): void
    {
        match (true) {
            $event instanceof CacheFlushEvent => $this->clearCache($event)
        };
    }

    /**
     * @param CacheFlushEvent $event
     * @return void
     */
    private function clearCache(CacheFlushEvent $event): void
    {
        $this->configLoader->clearCache();
    }
}
