<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use BackedEnum;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Exception\ShortNrConfigException;

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
