<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use TypedPatternEngine\Compiler\CompiledPattern;

abstract class AbstractProcessor implements ProcessorInterface
{
    protected function getRequiredEncodingFields(ConfigItemInterface $configItem): array
    {
        return array_unique(
            array_merge(
                array_values($configItem->getPattern()->getNamedGroups()),
                array_keys($configItem->getCondition())
            )
        );
    }
}
