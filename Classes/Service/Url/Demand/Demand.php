<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;

abstract class Demand implements DemandInterface
{
    protected ?ConfigItemInterface $configItem = null;
    /**
     * Extract and normalize the ShortNr segment from any URI
     *
     * Takes the last segment of the URI path to handle site base prefixes.
     * This allows ShortNr URLs to work regardless of TYPO3 site configuration.
     *
     * Examples:
     * - "/PAGE123" → "PAGE123"
     * - "/typo3-site/PAGE123-1" → "PAGE123-1"
     * - "/complex/path/EVENT456" → "EVENT456"
     *
     * @param string $uri Full URI path with potential query/fragment
     * @return string Clean ShortNr segment
     */
    protected static function normalizeShortNrUri(string $uri): string
    {
        // Remove query parameters and fragments
        $uri = strtok($uri, '?#');

        // Extract last segment (handles site base prefixes)
        return basename(trim($uri, '/'));
    }

    /**
     * @return ?ConfigItemInterface
     */
    public function getConfigItem(): ?ConfigItemInterface
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
