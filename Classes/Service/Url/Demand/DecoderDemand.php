<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use CPSIT\ShortNr\Config\DTO\FieldCondition;

class DecoderDemand extends Demand implements DecoderDemandInterface
{
    /**
     * @param string $shortNr provide a clean and sanitized shortNr NO URI
     */
    public function __construct(
        protected readonly string $shortNr
    )
    {}

    /**
     * clean and sanitized ShortNr
     *
     * @return string
     */
    public function getShortNr(): string
    {
        return $this->shortNr;
    }

    /**
     * @return FieldCondition[]
     */
    public function getConditions(): array
    {
        return $this->configItem->getConditions() ?? [];
    }
}
