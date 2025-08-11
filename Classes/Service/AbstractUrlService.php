<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Language\LanguageOverlayService;
use CPSIT\ShortNr\Service\Url\Condition\ConditionService;
use CPSIT\ShortNr\Service\Url\Processor\NotFoundProcessor;
use CPSIT\ShortNr\Service\Url\Processor\ProcessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class AbstractUrlService
{
    private array $cache = [];

    /**
     * @param iterable $processors
     * @param ConfigLoader $configLoader
     * @param ConditionService $conditionService
     * @param CacheManager $cacheManager
     * @param EventDispatcherInterface $eventDispatcher
     * @param LanguageOverlayService $languageOverlayService
     */
    public function __construct(
        protected readonly iterable $processors,
        protected readonly ConfigLoader $configLoader,
        protected readonly ConditionService $conditionService, // used in encoder
        protected readonly CacheManager $cacheManager, // used in decoder
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly LanguageOverlayService $languageOverlayService,
    )
    {}

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
