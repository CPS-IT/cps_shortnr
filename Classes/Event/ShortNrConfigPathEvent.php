<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Event;

/**
 * Event for collecting additional configuration file paths from other extensions
 *
 * This event is dispatched when building the configuration and allows
 * other extensions to register their own YAML configuration files
 * that will be merged with the base configuration.
 */
final class ShortNrConfigPathEvent
{
    /**
     * @var array<int, array{path: string, priority: int}>
     */
    private array $configPaths = [];

    /**
     * Add a configuration file path
     *
     * @param string $path The path to the YAML config file (e.g., 'EXT:my_extension/Configuration/shortnr.yaml')
     * @param int $priority Higher priority configs override lower ones (default: 10)
     */
    public function addConfigPath(string $path, int $priority = 10): void
    {
        $this->configPaths[$priority][] = $path;
    }

    /**
     * Get all registered configuration paths sorted by priority
     *
     * @return array<string> Array of config file paths sorted by priority (highest first)
     */
    public function getConfigPaths(): array
    {
        // Sort by priority (descending) to ensure proper override order
        $paths = $this->configPaths;
        ksort($paths, SORT_NUMERIC);

        $flatList = [];
        foreach ($paths as $pathSubList) {
            array_push($flatList, ...$pathSubList);
        }

        return $flatList;
    }
}
