<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;

interface DemandInterface
{
    /**
     * @return ?ConfigItemInterface
     */
    public function getConfigItem(): ?ConfigItemInterface;

    /**
     * @param ConfigItemInterface $configItem
     */
    public function setConfigItem(ConfigItemInterface $configItem): void;
}
