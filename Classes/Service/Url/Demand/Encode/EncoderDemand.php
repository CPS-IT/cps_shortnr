<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand\Encode;

use CPSIT\ShortNr\Service\Url\Demand\Demand;

abstract class EncoderDemand extends Demand implements EncoderDemandInterface
{
    /**
     * true = generate absolute URL, false = URI
     *
     * @var bool
     */
    protected bool $absolute = false;

    protected ?int $languageId = null;

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
}
