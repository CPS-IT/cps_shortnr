<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use TypedPatternEngine\Compiler\MatchResult;

class PluginProcessor implements ProcessorInterface
{
    /**
     * @return string
     */
    public function getType(): string
    {
        return 'plugin';
    }

    /**
     * @param ConfigItemInterface $configItem
     * @param MatchResult $matchResult
     * @return string|null
     */
    public function decode(ConfigItemInterface $configItem, MatchResult $matchResult): ?string
    {
        return null;
    }
}
