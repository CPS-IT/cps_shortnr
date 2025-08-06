<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use CPSIT\ShortNr\Exception\ShortNrConfigException;
use BackedEnum;

interface ConfigInterface
{
    /**
     * Get all available config names (excluding _default)
     *
     * @return string[] List of config names like ['pages', 'plugins', 'events']
     */
    public function getConfigNames(): array;

    /**
     * Group config names by their regex patterns for route matching
     *
     * Creates a map where identical regex patterns are grouped together
     * to avoid duplicate regex processing during URL decoding.
     *
     * @return array<string, array> Map of regex pattern to config names
     *                              e.g., ['/^PAGE(\d+)$/' => ['pages', 'events']]
     */
    public function getUniqueRegexConfigNameGroup(): array;

    /**
     * Create a scoped config accessor for a specific config item
     *
     * @param string $name Config name (e.g., 'pages', 'plugins')
     * @return ConfigItemInterface Scoped accessor for the config item
     * @throws ShortNrConfigException When config name doesn't exist
     */
    public function getConfigItem(string $name): ConfigItemInterface;

    /**
     * Create a scoped config accessor for a specific config item based on the Prefix, since Prefixes are UNIQUE
     *
     * @param string $prefix
     * @return ConfigItemInterface
     * @throws ShortNrConfigException
     */
    public function getConfigItemByPrefix(string $prefix): ConfigItemInterface;

    /**
     * Get a config value with _default fallback (internal use only)
     *
     * Checks config[name][key] first, then config[_default][key] as fallback.
     * Used internally by ConfigItem delegate methods.
     *
     * @param string $name Config name to look up
     * @param string|BackedEnum $key Config key to retrieve
     * @return mixed return value, if not found return NULL, If value is a string trim will be applied
     *
     * @internal Use ConfigItem methods instead of calling this directly
     */
    public function getValue(string $name, string|BackedEnum $key): mixed;
}
