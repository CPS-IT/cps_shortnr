<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

interface EncoderDemandInterface extends DemandInterface
{
    /**
     * @return bool
     */
    public function isAbsolute(): bool;

    /**
     * @param bool $absolute
     * @return EncoderDemand
     */
    public function setAbsolute(bool $absolute): static;

    /**
     * @return int|null
     */
    public function getLanguageId(): ?int;

    /**
     * @param int|null $languageId
     * @return EncoderDemand
     */
    public function setLanguageId(?int $languageId): static;
}
