<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use BackedEnum;
use CPSIT\ShortNr\Config\Ast\Compiler\CompiledPattern;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use Generator;

class Config implements ConfigInterface
{
    private array $cache = [];

    /**
     * @param array $data
     */
    public function __construct(
        private readonly array $data
    )
    {}

    /**
     * [ConfigName => Pattern]
     *
     * @return Generator<string, string>
     * @throws ShortNrConfigException
     */
    public function getConfigNamePattern(): Generator
    {
        foreach ($this->getConfigItems() as $configItem) {
            yield $configItem->getName() => $configItem->getPattern();
        }
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
     * @return Generator<ConfigItemInterface> List of config items ... name as key
     * @throws ShortNrConfigException
     */
    public function getConfigItems(): Generator
    {
        foreach ($this->getConfigNames() as $configName) {
            yield $this->getConfigItem($configName);
        }
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

    /**
     * @param string $name
     * @return CompiledPattern
     * @throws ShortNrCacheException
     *
     * @internal
     */
    public function getPattern(string $name): CompiledPattern
    {
        return $this->data[ConfigInterface::COMPILED_PATTERN_KEY][$name] ?? throw new ShortNrCacheException('No Compiled Pattern Found for name: ' . $name);
    }

    /**
     * @inheritDoc
     */
    public function getPatterns(): Generator
    {
        yield from $this->data[ConfigInterface::COMPILED_PATTERN_KEY];
    }
}
