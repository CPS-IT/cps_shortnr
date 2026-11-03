<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Listener;

use CPSIT\ShortNr\Cache\ShortNrCacheInvalidator;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Extbase\Event\Persistence\EntityAddedToPersistenceEvent;
use TYPO3\CMS\Extbase\Event\Persistence\EntityRemovedFromPersistenceEvent;
use TYPO3\CMS\Extbase\Event\Persistence\EntityUpdatedInPersistenceEvent;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;

/**
 * Extbase writes bypass DataHandler (importers, scheduler tasks, frontend forms): collect the
 * tables and let ShortNrCacheInvalidator flush them once at the end of the request/process.
 */
final class ExtbasePersistenceListener
{
    public function __construct(
        private readonly DataMapFactory $dataMapFactory,
        private readonly ShortNrCacheInvalidator $invalidator,
        private readonly LoggerInterface $logger,
    ) {}

    #[AsEventListener('shortnr/extbase-entity-added')]
    public function added(EntityAddedToPersistenceEvent $event): void
    {
        $this->collect($event->getObject());
    }

    #[AsEventListener('shortnr/extbase-entity-updated')]
    public function updated(EntityUpdatedInPersistenceEvent $event): void
    {
        $this->collect($event->getObject());
    }

    #[AsEventListener('shortnr/extbase-entity-removed')]
    public function removed(EntityRemovedFromPersistenceEvent $event): void
    {
        $this->collect($event->getObject());
    }

    private function collect(object $object): void
    {
        try {
            $this->invalidator->invalidateTableDeferred($this->dataMapFactory->buildDataMap($object::class)->getTableName());
        } catch (Throwable $e) {
            // never break a write because of a cache, never stay silent
            $this->logger->error('[ShortNr] could not register ' . $object::class . ' for cache invalidation: ' . $e->getMessage());
        }
    }
}
