<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

class ConfigNameEncoderDemand extends EncoderDemand
{
    public function __construct(
        private readonly string $configName,
        private readonly int $uid
    )
    {}

    /**
     * @return string
     */
    public function getConfigName(): string
    {
        return $this->configName;
    }

    /**
     * @return int
     */
    public function getUid(): int
    {
        return $this->uid;
    }
}
