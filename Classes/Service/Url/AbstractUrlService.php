<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Url\Condition\ConditionService;
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
        $config = $this->configLoader->getConfig();
        return $this->conditionService->matchAny($this->normalizeShortNrUri($uri), $config);
    }

    /**
     * find the processor based on the type
     *
     * @param string $type
     * @return ProcessorInterface|null
     */
    protected function getProcessor(string $type): ?ProcessorInterface
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
     * trim the first and / last slash from our shortNr
     *
     * @param string $uri
     * @return string
     */
    protected function normalizeShortNrUri(string $uri): string
    {
        return trim($uri, '/');
    }
}
