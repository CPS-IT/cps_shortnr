<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\ConfigResolver;

use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;

class ConfigItemResolveProcessor
{
    /**
     * Parse shortNr and fill the result into the FieldConfig
     *
     * @param DecoderDemandInterface $demand
     * @param ConfigInterface $config
     * @return ConfigItemInterface|null
     * @throws ShortNrConfigException
     */
    public function parseDecoderDemand(DecoderDemandInterface $demand, ConfigInterface $config): ?ConfigItemInterface
    {
        return $this->parseShortNr($demand->getShortNr(), $config);
    }

    /**
     * @param string $shortNr
     * @param ConfigInterface $config
     * @return ConfigItemInterface|null
     * @throws ShortNrConfigException
     */
    private function parseShortNr(string $shortNr, ConfigInterface $config): ?ConfigItemInterface
    {
        foreach ($config->getUniqueRegexConfigNameGroup() as $regex => $configNames) {
            if (preg_match($regex, $shortNr, $matches) !== false) {
                // need a flat array with KEY = match group id and value = match value
                unset($matches[0]);
                if (($configItem = $this->processMatches($matches, $configNames, $config)) instanceof ConfigItemInterface) {
                    foreach ($configItem->getConditions() as $fieldCondition) {
                        $fieldCondition->processMatches($matches);
                    }
                    return $configItem;
                }
            }
        }

        return null;
    }

    /**
     * @param array $matches
     * @param array $possibleConfigNames
     * @param ConfigInterface $config
     * @return ConfigItemInterface|null
     * @throws ShortNrConfigException
     */
    private function processMatches(array $matches, array $possibleConfigNames, ConfigInterface $config): ?ConfigItemInterface
    {
        $prefixList = $config->getPrefixFieldConditions();
        foreach ($possibleConfigNames as $name) {
            if (isset($prefixList[$name])) {
                $prefixFieldCondition = $prefixList[$name];
                if ($prefixFieldCondition->processMatches($matches)) {
                    // fieldName is the PrefixName
                    $prefixName = $prefixFieldCondition->getFieldName();
                    foreach ($prefixFieldCondition->getProcessedMatches() as $processedMatch) {
                        if (strtolower($processedMatch->getValue()) === $prefixName) {
                            return $config->getConfigItemByPrefix($prefixName);
                        }
                    }
                }
            }
        }

        return null;
    }
}
