<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Event;

use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;

class ShortNrBeforeProcessorDecodingEvent
{
    public function __construct(
        private readonly DecoderDemandInterface $demand
    )
    {}

    /**
     * @return DecoderDemandInterface
     */
    public function getDemand(): DecoderDemandInterface
    {
        return $this->demand;
    }
}
