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

        $configItems = $this->configNormalizerService->getConfigItemForDemand($demand);
        if ($demand->noCache() || ($cacheKey = $demand->getCacheKey()) === null) {
            return $this->encodeWithConfigItems($demand, $configItems);
        }

        // a save on a table of these config items can change the short number (pattern or
        // condition fields), ClearCacheDataHandlerHook flushes by these tags
        $tags = [CacheTags::ALL, CacheTags::URI, CacheTags::ENCODE];
        foreach ($configItems as $configItem) {
            if ($table = $configItem->getTableName()) {
                $tags[] = CacheTags::forTable($table);
            }
        }

        return $this->getCacheManager()->getType3CacheValue(
            sprintf('encode-%s', $cacheKey),
            fn() => $this->encodeWithConfigItems($demand, $configItems),
            ttl: 604_800, // one week
            tags: array_values(array_unique($tags))
        );
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
