<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer;

use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Url\Demand\ConfigNameEncoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer\DTO\EncoderDemandNormalizationResult;
use CPSIT\ShortNr\Service\Url\ConfigResolver\MatchResult;

class ConfigNameDemandNormalizer implements EncodingDemandNormalizerInterface
{
    /**
     * @param EncoderDemandInterface $demand
     * @param ConfigInterface $config
     * @return bool
     */
    public function supports(EncoderDemandInterface $demand, ConfigInterface $config): bool
    {
        return (
            $demand instanceof ConfigNameEncoderDemand &&
            !empty($demand->getUid()) &&
            $config->hasConfigItemName($demand->getConfigName())
        );
    }

    /**
     * @param EncoderDemandInterface $demand
     * @param ConfigInterface $config
     * @return EncoderDemandNormalizationResult|null
     * @throws ShortNrConfigException
     */
    public function normalize(EncoderDemandInterface $demand, ConfigInterface $config): ?EncoderDemandNormalizationResult
    {
        // TODO: load all MATCH-RELEVANT information too same as in ObjectDemandNormalizer
        // here it must be more given via DEMAND, and then validate if all data is given
        if (!$demand instanceof ConfigNameEncoderDemand) {
            return null;
        }

        $configItem = $config->getConfigItem($demand->getConfigName());

        $mr = new MatchResult($configItem, []);

        return new EncoderDemandNormalizationResult([], $configItem);
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 100;
    }
}
