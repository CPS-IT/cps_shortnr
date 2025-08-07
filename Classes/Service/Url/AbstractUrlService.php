<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Url\Condition\ConditionService;
use CPSIT\ShortNr\Service\Url\Processor\NotFoundProcessor;
use CPSIT\ShortNr\Service\Url\Processor\ProcessorInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractUrlService
{
    private array $cache = [];

    /**
     * @param iterable $processors
     * @param ConfigLoader $configLoader
     * @param ConditionService $conditionService
     * @param CacheManager $cacheManager
     */
    public function __construct(
        protected readonly iterable $processors,
        protected readonly ConfigLoader $configLoader,
        protected readonly ConditionService $conditionService, // used in encoder
        protected readonly CacheManager $cacheManager // used in decoder
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
     * @param ServerRequestInterface $request
     * @return bool
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function isShortNrRequest(ServerRequestInterface $request): bool
    {
        return $this->isShortNr($request->getUri()->getPath());
    }

    /**
     * fast check if the given uri is a shortNr
     *
     * @param string $uri uri can be like /PAGE123 or /PAGE123-1 (for english)
     * @return bool
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function isShortNr(string $uri): bool
    {
        // fast regex ONLY pre-compiled check like /(regex1|regex2|regex3)/
        $compiledRegex = $this->configLoader->getCompiledRegexForFastCheck();
        if ($compiledRegex === null) return false;

        return preg_match($compiledRegex, $this->normalizeShortNrUri($uri)) === 1;
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
        $type = $configItem->getValue(ConfigEnum::NotFoundType) ?? NotFoundProcessor::NOT_FOULD_PROCESSOR_TYPE;
        if($type !== NotFoundProcessor::NOT_FOULD_PROCESSOR_TYPE) {

            return $this->getProcessorByType($type) ?? $this->getProcessorByType(NotFoundProcessor::NOT_FOULD_PROCESSOR_TYPE);
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

    /**
     * Extract and normalize the ShortNr segment from any URI
     *
     * Takes the last segment of the URI path to handle site base prefixes.
     * This allows ShortNr URLs to work regardless of TYPO3 site configuration.
     *
     * Examples:
     * - "/PAGE123" → "PAGE123"
     * - "/typo3-site/PAGE123-1" → "PAGE123-1"
     * - "/complex/path/EVENT456" → "EVENT456"
     *
     * @param string $uri Full URI path with potential query/fragment
     * @return string Clean ShortNr segment
     */
    protected function normalizeShortNrUri(string $uri): string
    {
        // Remove query parameters and fragments
        $uri = strtok($uri, '?#');

        // Extract last segment (handles site base prefixes)
        return basename(trim($uri, '/'));
    }
}
