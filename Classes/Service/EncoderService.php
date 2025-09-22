<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service;

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
        $cacheKey = $demand->getCacheKey();
        if ($cacheKey === null) {
            return $this->encodeWithDemand($demand);
        }

        return $this->getCacheManager()->getType3CacheValue(
            sprintf('encode-%s', $cacheKey),
            fn() => $this->encodeWithDemand($demand),
            ttl: 604_800, // one week
            tags: ['all', 'uri', 'encode']
        );
    }

    /**
     * @param EncoderDemandInterface $demand
     * @return string|null
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    private function encodeWithDemand(EncoderDemandInterface $demand): ?string
    {
        $configItems = $this->configNormalizerService->getConfigItemForDemand($demand);
        foreach ($configItems as $configItem) {
            $result = $this->getProcessor($configItem)->encode($configItem, $demand);

            if (!empty($result)) {
                return $result;
            }
        }

        return null;
    }
}
