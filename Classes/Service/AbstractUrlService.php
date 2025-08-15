<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Event\ShortNrPatternParserBootEvent;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Url\Processor\NotFoundProcessor;
use CPSIT\ShortNr\Service\Url\Processor\ProcessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class AbstractUrlService
{
    private array $cache = [];
    private iterable $processors;
    private ConfigLoader $configLoader;
    private CacheManager $cacheManager;
    private EventDispatcherInterface $eventDispatcher;

    public function setProcessors(iterable $processors)
    {
        $this->processors = $processors;
    }

    /**
     * @param ConfigLoader $configLoader
     */
    public function setConfigLoader(ConfigLoader $configLoader): void
    {
        $this->configLoader = $configLoader;
    }

    /**
     * @param CacheManager $cacheManager
     */
    public function setCacheManager(CacheManager $cacheManager): void
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return ConfigInterface
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    protected function getConfig(): ConfigInterface
    {
        return $this->configLoader->getConfig();
    }

    /**
     * @return ConfigLoader
     */
    protected function getConfigLoader(): ConfigLoader
    {
        if (!isset($this->cache['finishFirstTimeCacheCall'])) {
            /** @var ShortNrPatternParserBootEvent $event */
            $event = $this->getEventDispatcher()->dispatch(new ShortNrPatternParserBootEvent());
            $this->configLoader->setPatternTypeRegistry($event->getTypeRegistry());
            $this->cache['finishFirstTimeCacheCall'] = true;
        }

        return $this->configLoader;
    }

    /**
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    /**
     * @return CacheManager
     */
    public function getCacheManager(): CacheManager
    {
        return $this->cacheManager;
    }

    /**
     * find the processor based on the type
     *
     * @param ConfigItemInterface $configItem
     * @return ProcessorInterface|null
     */
    protected function getProcessor(ConfigItemInterface $configItem): ?ProcessorInterface
    {
        return $this->getProcessorByType($configItem->getType());

    }

    /**
     * @param ConfigItemInterface $configItem
     * @return ProcessorInterface|null
     */
    protected function getNotFoundProcessor(ConfigItemInterface $configItem): ?ProcessorInterface
    {
        // hidden config for overwrite the notFound Processor with a different Processor
        $type = $configItem->getValue(ConfigEnum::NotFoundType) ?? NotFoundProcessor::NOT_FOUND_PROCESSOR_TYPE;
        if($type !== NotFoundProcessor::NOT_FOUND_PROCESSOR_TYPE) {

            return $this->getProcessorByType($type) ?? $this->getProcessorByType(NotFoundProcessor::NOT_FOUND_PROCESSOR_TYPE);
        }

        return $this->getProcessorByType($type);
    }

    /**
     * @param string $type
     * @return ProcessorInterface|null
     */
    private function getProcessorByType(string $type): ?ProcessorInterface
    {
        if (isset($this->cache['processor'][$type])) {
            return $this->cache['processor'][$type];
        }

        foreach ($this->processors as $processor) {
            if ($processor->getType() === $type) {
                return $this->cache['processor'][$type] = $processor;
            }
        }
        return null;
    }
}
