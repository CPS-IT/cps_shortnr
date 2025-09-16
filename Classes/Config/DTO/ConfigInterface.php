<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use BackedEnum;
use Generator;
use TypedPatternEngine\Compiler\CompiledPattern;

interface ConfigInterface
{
    /**
     * [ConfigName => Pattern]
     *
     * @return Generator<string, string>
     */
    public function getConfigNamePattern(): iterable;

    /**
     * @param string $name
     * @return bool true if the name exists, false if the name not exists
     */
    public function hasConfigItemName(string $name): bool;

    /**
     * Get all available config names (excluding _default)
     *
     * all configNames will be in a key value list where the key and the value is the same configName
     *
     * @return array<string, string> List of config names like ['pages' => 'pages', 'myPlugins' => 'myPlugins', 'events' => 'events']
     */
    public function getConfigNames(): array;

    /**
     * Get all available config names (excluding _default)
     *
     * @return Generator<ConfigItemInterface> List of config items ... name as key
     */
    public function getConfigItems(): iterable;

    /**
     * Create a scoped config accessor for a specific config item
     *
     * @param string $name Config name (e.g., 'pages', 'plugins')
     * @return ConfigItemInterface Scoped accessor for the config item
     * @throws ShortNrConfigException When config name doesn't exist
     */
    public function getConfigItem(string $name): ConfigItemInterface;

    /**
     * Create a scoped config accessor for a specific config items based on the TableName
     *
     * @param string $tableName
     * @return array<ConfigItemInterface>
     * @throws ShortNrConfigException
     */
    public function getConfigItemsByTableName(string $tableName): array;

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

    /**
     * get the compiled Pattern
     *
     * @param string $name
     * @return CompiledPattern
     * @throws ShortNrCacheException
     *
     * @internal Use ConfigItem methods instead of calling this directly
     */
    public function getPattern(string $name): CompiledPattern;

    /**
     * [configItemName => CompiledPattern]
     *
     * return all Compiled Patterns
     * @return Generator<string ,CompiledPattern>
     */
    public function getPatterns(): Generator;
}
