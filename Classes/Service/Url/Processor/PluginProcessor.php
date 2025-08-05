<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Service\Url\Condition\DTO\ConfigMatchCandidate;
use CPSIT\ShortNr\Service\Url\Processor\DTO\ProcessorDecodeResult;
use CPSIT\ShortNr\Service\Url\Processor\DTO\ProcessorDecodeResultInterface;

class PluginProcessor implements ProcessorInterface
{
    /**
     * @return string
     */
    public function getType(): string
    {
        return 'plugin';
    }

    public function decode(ConfigMatchCandidate $candidate, ConfigItemInterface $config): ProcessorDecodeResultInterface
    {
        return new ProcessorDecodeResult(null);
    }
}
