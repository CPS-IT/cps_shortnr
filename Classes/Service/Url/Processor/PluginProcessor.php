<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\Url\Condition\DTO\ConfigMatchCandidate;

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
     * @param ConfigMatchCandidate $candidate
     * @param ConfigItemInterface $config
     * @return string|null
     */
    public function decode(ConfigMatchCandidate $candidate, ConfigItemInterface $config): ?string
    {
        return null;
    }
}
