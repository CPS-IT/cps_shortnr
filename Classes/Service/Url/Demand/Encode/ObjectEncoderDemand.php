<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand\Encode;

class ObjectEncoderDemand extends EncoderDemand
{
    public function __construct(
        private readonly object $entity
    )
    {}

    /**
     * @return object
     */
    public function getObject(): object
    {
        return $this->entity;
    }
}
