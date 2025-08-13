<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer;

use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Service\Url\Demand\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer\DTO\EncoderDemandNormalizationResult;
use CPSIT\ShortNr\Traits\PriorityAwareInterface;

interface EncodingDemandNormalizerInterface extends PriorityAwareInterface
{
    /**
     * @param EncoderDemandInterface $demand
     * @param ConfigInterface $config
     * @return bool
     */
    public function supports(EncoderDemandInterface $demand, ConfigInterface $config): bool;

    /**
     * @param EncoderDemandInterface $demand
     * @param ConfigInterface $config
     * @return EncoderDemandNormalizationResult|null
     */
    public function normalize(EncoderDemandInterface $demand, ConfigInterface $config): ?EncoderDemandNormalizationResult;
}
