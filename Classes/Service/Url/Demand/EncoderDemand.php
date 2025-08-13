<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer\DTO\EncoderDemandNormalizationResult;

abstract class EncoderDemand extends Demand implements EncoderDemandInterface
{
    /**
     * true = generate absolute URL, false = URI
     *
     * @var bool
     */
    protected bool $absolute = false;

    protected ?int $languageId = null;
    protected ?EncoderDemandNormalizationResult $normalizationResult;

    /**
     * @return bool
     */
    public function isAbsolute(): bool
    {
        return $this->absolute;
    }

    /**
     * @param bool $absolute
     * @return EncoderDemand
     */
    public function setAbsolute(bool $absolute): static
    {
        $this->absolute = $absolute;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLanguageId(): ?int
    {
        return $this->languageId;
    }

    /**
     * @param int|null $languageId
     * @return EncoderDemand
     */
    public function setLanguageId(?int $languageId): static
    {
        $this->languageId = $languageId;
        return $this;
    }

    /**
     * @return EncoderDemandNormalizationResult|null
     */
    public function getNormalizationResult(): ?EncoderDemandNormalizationResult
    {
        return $this->normalizationResult;
    }

    /**
     * @param EncoderDemandNormalizationResult|null $normalizationResult
     * @return EncoderDemand
     */
    public function setNormalizationResult(?EncoderDemandNormalizationResult $normalizationResult): static
    {
        $this->normalizationResult = $normalizationResult;
        return $this;
    }
}
