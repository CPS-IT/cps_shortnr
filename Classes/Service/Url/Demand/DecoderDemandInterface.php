<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use CPSIT\ShortNr\Config\DTO\FieldCondition;

interface DecoderDemandInterface extends DemandInterface
{
    /**
     * clean and sanitized ShortNr
     *
     * @return string
     */
    public function getShortNr(): string;

    /**
     * get resolved conditions
     *
     * @return array<string, FieldCondition>
     */
    public function getConditions(): array;
}
