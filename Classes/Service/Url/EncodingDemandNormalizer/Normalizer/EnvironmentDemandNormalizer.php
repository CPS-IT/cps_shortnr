<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer;

use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Exception\ShortNrDemandNormalizationException;
use CPSIT\ShortNr\Service\Url\Demand\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer\DTO\EncoderDemandNormalizationResult;

class EnvironmentDemandNormalizer implements EncodingDemandNormalizerInterface
{
    /**
     * @param EncoderDemandInterface $demand
     * @param ConfigInterface $config
     * @return bool
     */
    public function supports(EncoderDemandInterface $demand, ConfigInterface $config): bool
    {
        return false;
    }

    /**
     * @param EncoderDemandInterface $demand
     * @param ConfigInterface $config
     * @return EncoderDemandNormalizationResult
     */
    public function normalize(EncoderDemandInterface $demand, ConfigInterface $config): EncoderDemandNormalizationResult
    {
        throw new ShortNrDemandNormalizationException('WIP');
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 1;
    }
}
