<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer;

use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Config\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Url\Demand\ConfigNameEncoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer\DTO\EncoderDemandNormalizationResult;
use phpDocumentor\Reflection\Types\This;

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

        $matchFieldConditions = [];
        foreach ($configItem->getConditions() as $fieldname => $condition) {
            if ($condition->hasMatches()) {
                $matchFieldConditions[$fieldname] = $condition;
            }
        }



        return new EncoderDemandNormalizationResult([], $configItem);
    }

    /**
     * @param array<string, FieldConditionInterface> $conditions conditions that need to be populated
     * @param array<string, mixed> $matches data to populate the conditions
     * @return array<string, FieldConditionInterface> return the remaining open conditions that has not matched
     */
    private function populateConditionMatches(array $conditions, array $matches): array
    {
        foreach ($matches as $fieldName => $value) {
            $condition = $conditions[$fieldName] ?? null;
            if ($condition instanceof FieldConditionInterface) {
                $condition->processMatches($matches);
            }
        }

        return $conditions;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 100;
    }
}
