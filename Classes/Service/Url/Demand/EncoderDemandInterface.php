<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer\DTO\EncoderDemandNormalizationResult;

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

    public function setNormalizationResult(EncoderDemandNormalizationResult $normalizationResult): static;

    public function getNormalizationResult(): ?EncoderDemandNormalizationResult;
}
