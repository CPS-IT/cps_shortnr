<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer\DTO;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;

class EncoderDemandNormalizationResult
{
    public function __construct(
        private readonly array $matchData,
        private readonly ConfigItemInterface $configItem
    )
    {}

    /**
     * @return array
     */
    public function getMatchData(): array
    {
        return $this->matchData;
    }

    /**
     * @return ConfigItemInterface
     */
    public function getConfigItem(): ConfigItemInterface
    {
        return $this->configItem;
    }
}
