<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Event;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;

final class ShortNrEncodingConfigItemEvent
{
    /**
     * @param ConfigItemInterface[] $configItems
     */
    public function __construct(
        private array $configItems
    ) {}

    /**
     * @return ConfigItemInterface[]
     */
    public function getConfigItems(): array
    {
        return $this->configItems;
    }

    /**
     * @param ConfigItemInterface[] $configItems
     */
    public function setConfigItems(array $configItems): void
    {
        $this->configItems = $configItems;
    }
}
