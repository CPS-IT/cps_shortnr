<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand\Encode;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

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

    /**
     * @inheritDoc
     */
    public function getCacheKey(): ?string
    {
        if (!($this->entity instanceof AbstractEntity)) {
            // cannot cache
            return null;
        }

        return $this->entity::class.'#'.$this->entity->getUid().'@'.$this->getLanguageId().'('. $this->isAbsolute() ? 'ABS':'NO-ABS' .')';
    }
}
