<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

class ObjectEncoderDemand extends EncoderDemand
{
    public function __construct(
        private readonly object $object
    )
    {}

    /**
     * @return object
     */
    public function getObject(): object
    {
        return $this->object;
    }
}
