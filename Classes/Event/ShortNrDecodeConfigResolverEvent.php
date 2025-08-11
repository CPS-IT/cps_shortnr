<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Event;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;
use CPSIT\ShortNr\Service\Url\Regex\MatchResult;

class ShortNrDecodeConfigResolverEvent
{
    private ?ConfigItemInterface $configItem = null;
    private ?MatchResult $matchResult = null;

    public function __construct(
        private readonly DecoderDemandInterface $decoderDemand
    )
    {}

    /**
     * @return DecoderDemandInterface
     */
    public function getDecoderDemand(): DecoderDemandInterface
    {
        return $this->decoderDemand;
    }

    /**
     * @param ConfigItemInterface|null $configItem
     * @return void
     */
    public function setConfigItem(?ConfigItemInterface$configItem): void
    {
        $this->configItem = $configItem;
    }

    /**
     * @return ConfigItemInterface|null
     */
    public function getConfigItem(): ?ConfigItemInterface
    {
        return $this->configItem;
    }

    /**
     * @return MatchResult|null
     */
    public function getMatchResult(): ?MatchResult
    {
        return $this->matchResult;
    }

    /**
     * @param MatchResult|null $matchResult
     */
    public function setMatchResult(?MatchResult $matchResult): void
    {
        $this->matchResult = $matchResult;
    }
}
