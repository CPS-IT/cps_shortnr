<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;

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
     * @param DecoderDemandInterface $demand
     * @return string|null
     */
    public function decode(DecoderDemandInterface $demand): ?string
    {
        return null;
    }
}
