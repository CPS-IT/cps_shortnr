<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Event;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;

class ShortNrConfigItemProcessedEvent
{
    public function __construct(
        private readonly DecoderDemandInterface $demand,
        private ConfigItemInterface $configItem
    )
    {}

    /**
     * @return DecoderDemandInterface
     */
    public function getDemand(): DecoderDemandInterface
    {
        return $this->demand;
    }

    /**
     * @return ConfigItemInterface
     */
    public function getConfigItem(): ConfigItemInterface
    {
        return $this->configItem;
    }

    /**
     * @param ConfigItemInterface $configItem
     */
    public function setConfigItem(ConfigItemInterface $configItem): void
    {
        $this->configItem = $configItem;
    }
}
