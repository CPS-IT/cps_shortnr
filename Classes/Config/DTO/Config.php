<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use BackedEnum;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Exception\ShortNrConfigException;

class Config implements ConfigInterface
{
    public const PREFIX_MAP_KEY = '__prefix_map';
    public const SORTED_REGEX_LIST_KEY = '__sorted_regex_list';

    /**
     * [prefix => [
     *      'name' => ConfigName,
     *      ConfigEnum::PrefixMatch->value => 'ConfigPrefixMatchGroup'
     *    ]
     * ]
     *
     * @var array<string, array<string, string>>
     */
    private readonly array $prefixMap;
    private readonly array $regexList;

    private array $cache = [];

    /**
     * @param array $data
     * @throws ShortNrConfigException
     */
    public function __construct(
        private readonly array $data
    )
    {
        // we use the map as indicator to know if the Config is "empty"
        if (empty($this->data[static::PREFIX_MAP_KEY]) || empty($this->data[static::SORTED_REGEX_LIST_KEY])) {
            throw new ShortNrConfigException('Malformed Configuration detected (Regex / Prefix maps could not be found)');
        }
        $this->prefixMap = $this->data[static::PREFIX_MAP_KEY];

        // Use pre-built sorted regex list from ConfigLoader
        $this->regexList = $this->data[static::SORTED_REGEX_LIST_KEY];
    }

    /**
     * @param string $name
     * @return bool true if the name exists, false if the name not exists
     */
    public function hasConfigItemName(string $name): bool
    {
        return (bool)($this->getConfigNames()[$name] ?? false);
    }

    /**
     * @return array<string, string>
     */
    public function getConfigNames(): array
    {
        return $this->cache['configNames'] ??= array_combine($names = array_values(array_filter(
            array_keys($this->data[ConfigEnum::ENTRYPOINT->value] ?? []),
            fn($name) : bool => ($name !== ConfigEnum::DEFAULT_CONFIG->value)
        )), $names);
    }

    /**
     * Get all available config names (excluding _default)
     *
     * @return iterable<ConfigItemInterface> List of config items ... name as key
     * @throws ShortNrConfigException
     */
    public function getConfigItems(): iterable
    {
        foreach ($this->getConfigNames() as $configName) {
            yield $this->getConfigItem($configName);
        }
    }

    /**
     * Get pre-built regex list grouped by regex pattern, sorted by priority (high to low)
     *
     * @return array<string, array>
     */
    public function getUniqueRegexConfigNameGroup(): array
    {
        return $this->regexList;
    }

    /**
     * @param string $name
     * @return ConfigItemInterface
     * @throws ShortNrConfigException
     */
    public function getConfigItem(string $name): ConfigItemInterface
    {
        $configNames = $this->getConfigNames();
        if (!in_array($name, $configNames, true)) {
            throw new ShortNrConfigException(sprintf('Config name "%s" does not exist. found in config: (%s))', $name, implode(', ', $configNames)));
        }

        return $this->cache['configItem'][$name] ??= new ConfigItem($name, $this);
    }

    /**
     * return the ConfigItem based on the Prefix, since Prefixes are UNIQUE
     *
     * @param string $prefix
     * @return ConfigItemInterface
     * @throws ShortNrConfigException
     */
    public function getConfigItemByPrefix(string $prefix): ConfigItemInterface
    {
        // the configLoader uses strtolower() to generate the map, (case-insensitive)
        ['name' => $configName] = $this->prefixMap[strtolower($prefix)] ?? ['name' => null];
        if ($configName === null) {
            throw new ShortNrConfigException('Prefix \''. $prefix .'\' does not exist in PrefixMap does not exist, available prefixes are: '. implode(', ', array_keys($this->prefixMap)));
        }

        try {
            return $this->getConfigItem($configName);
        } catch (ShortNrConfigException $e) {
            throw new ShortNrConfigException(sprintf('Prefix %s that is defined in PrefixMap and resolves to Name %s did not exists', $prefix, $configName), $e->getCode(), $e);
        }
    }

    /**
     * [configName = FieldConditionInterface]
     *
     * @return array<string, FieldConditionInterface>
     */
    public function getPrefixFieldConditions(): array
    {
        if (isset($this->cache['prefixFieldConditions'])) {
            return $this->cache['prefixFieldConditions'];
        }
        $nameKey = 'name';
        $prefixMatchKey = ConfigEnum::PrefixMatch->value;

        $list = [];
        foreach ($this->prefixMap as $prefix => $struct) {
            $list[$struct[$nameKey]] = new FieldCondition($prefix, $struct[$prefixMatchKey]);
        }
        return $this->cache['prefixFieldConditions'] = $list;
    }

    /**
     * @param string $tableName
     * @return ConfigItemInterface[]
     * @throws ShortNrConfigException
     */
    public function getConfigItemsByTableName(string $tableName): array
    {
        if (!isset($this->cache['tableNameList'])) {
            $this->cache['tableNameList'] = $this->getConfigItemsByTableNameList();
        }

        return $this->cache['tableNameList'][$tableName] ?? [];
    }

    /**
     * @return array<string, ConfigItemInterface[]>
     * @throws ShortNrConfigException
     */
    private function getConfigItemsByTableNameList(): array
    {
        $tableList = [];
        foreach ($this->getConfigItems() as $configItem) {
            $tableList[$configItem->getTableName()][] = $configItem;
        }

        return $tableList;
    }

    // Core route properties

    /**
     *
     * @param string $name
     * @param string|ConfigEnum $key
     * @return mixed return value, if not found return NULL, If value is a string trim will be applied
     *
     * @internal
     */
    public function getValue(string $name, string|BackedEnum $key): mixed
    {
        if ($key instanceof BackedEnum) {
            $key = $key->value;
        }
        $entryPoint = ConfigEnum::ENTRYPOINT->value;
        $defaultValue = ConfigEnum::DEFAULT_CONFIG->value;

        return $this->data[$entryPoint][$name][$key] ?? $this->data[$entryPoint][$defaultValue][$key] ?? null;
    }
}
