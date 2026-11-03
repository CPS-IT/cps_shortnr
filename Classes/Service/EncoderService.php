<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service;

use CPSIT\ShortNr\Cache\CacheTags;
use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Url\Demand\Encode\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\EncodeNormalizer\EncodeConfigNormalizerService;

class EncoderService extends AbstractUrlService
{
    private const CACHE_LIFETIME = 604_800; // one week

    public function __construct(
        private readonly EncodeConfigNormalizerService $configNormalizerService,
    )
    {}

    /**
     * @param EncoderDemandInterface $demand
     * @return string|null
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function encode(EncoderDemandInterface $demand): ?string
    {
        // be_user session detected, auto disable cache
        // CLI, scheduler and early middlewares have no BE_USER global at all
        if (($GLOBALS['BE_USER'] ?? null)?->user !== null) {
            $demand->setNoCache(true);
        }

        if ($demand->noCache() || ($cacheKey = $demand->getCacheKey()) === null) {
            return $this->encodeWithConfigItems($demand, $this->configNormalizerService->getConfigItemForDemand($demand));
        }

        // a hit must stay cheap: share links render on every page, resolving the config items
        // dispatches ShortNrEncodingConfigItemEvent to the consumers' listeners
        $cacheKey = sprintf('encode-%s', $cacheKey);
        $cached = $this->getCacheManager()->get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        $configItems = $this->configNormalizerService->getConfigItemForDemand($demand);
        $result = $this->encodeWithConfigItems($demand, $configItems);
        if (is_string($result) && $result !== '') {
            // a save on a table of these config items can change the short number (pattern or
            // condition fields), ShortNrCacheInvalidator flushes by these tags
            $tags = [CacheTags::ALL, CacheTags::URI, CacheTags::ENCODE];
            foreach ($configItems as $configItem) {
                if ($table = $configItem->getTableName()) {
                    $tags[] = CacheTags::forTable($table);
                }
            }
            $this->getCacheManager()->set($cacheKey, $result, self::CACHE_LIFETIME, array_values(array_unique($tags)));
        }

        return $result;
    }

    /**
     * @param EncoderDemandInterface $demand
     * @param array<ConfigItemInterface> $configItems
     * @return string|null
     */
    private function encodeWithConfigItems(EncoderDemandInterface $demand, array $configItems): ?string
    {
        foreach ($configItems as $configItem) {
            $result = $this->getProcessor($configItem)->encode($configItem, $demand);

            if (!empty($result)) {
                return $result;
            }
        }

        return null;
    }
}
