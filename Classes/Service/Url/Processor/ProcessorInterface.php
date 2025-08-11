<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;

interface ProcessorInterface
{
    /**
     * return the type that is matched with the config
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Return a string (full URI) or throws ShortNrNotFoundException to trigger the notFound Fallback
     *
     * @param DecoderDemandInterface $demand
     * @return string|null
     * @throws ShortNrNotFoundException
     */
    public function decode(DecoderDemandInterface $demand): ?string;
}
