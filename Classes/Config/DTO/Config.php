<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use BackedEnum;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Exception\ShortNrConfigException;

class Config implements ConfigInterface
{
    public const PREFIX_MAP_KEY = '__prefix_map';

    private readonly array $prefixMap;

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
        if (empty($this->data[static::PREFIX_MAP_KEY])) {
            throw new ShortNrConfigException('No Configuration Items found (Prefix Map is Empty)');
        }
        $this->prefixMap = $this->data[static::PREFIX_MAP_KEY];
    }

    /**
     * @return string[]
     */
    public function getConfigNames(): array
    {
        return $this->cache['configNames'] ??= array_values(array_filter(
            array_keys($this->data[ConfigEnum::ENTRYPOINT->value] ?? []),
            fn($name) : bool => ($name !== ConfigEnum::DEFAULT_CONFIG->value)
        ));
    }

    /**
     * gather all regex of all names and create a regex per name list.
     *
     * @return array<string, array>
     */
    public function getUniqueRegexConfigNameGroup(): array
    {
        if (!empty($this->cache['regexList'])) {
            return $this->cache['regexList'];
        }

        $regexNameList = [];
        foreach ($this->getConfigNames() as $configName) {
            $regexNameList[$this->getRegex($configName)][] = $configName;
        }

        return $this->cache['regexList'] = $regexNameList;
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
        $configName = $this->prefixMap[strtolower($prefix)] ?? null;
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
     * @param string $name
     * @return string|null return the regex of the given type fall bock on _default if not set.
     *
     * if no regex at all configured NULL is returned
     */
    private function getRegex(string $name): ?string
    {
        return $this->getValue($name, ConfigEnum::Regex);
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
