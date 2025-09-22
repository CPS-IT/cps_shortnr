<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand\Encode;

use CPSIT\ShortNr\Service\Url\Demand\DemandInterface;

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

    /**
     * @return string|null
     */
    public function getCacheKey(): ?string;
}
