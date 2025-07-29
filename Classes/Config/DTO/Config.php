<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use BackedEnum;
use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use StringBackedEnum;

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
     * @return string|null return the regex of the given type fall bock on _default if not set.
     *
     * if no regex at all configured NULL is returned
     */
    public function getRegex(string $name): ?string
    {
        return $this->getValue($name, ConfigEnum::Regex);
    }

    // Core route properties

    /**
     * @param string $name
     * @return string|null
     */
    public function getPrefix(string $name): ?string
    {
        return $this->getValue($name, ConfigEnum::Prefix);
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getType(string $name): ?string
    {
        return $this->getValue($name, ConfigEnum::Type);
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getTableName(string $name): ?string
    {
        return $this->getValue($name, ConfigEnum::Table);
    }

    /**
     * @param string $name
     * @return array
     */
    public function getCondition(string $name): array
    {
        return $this->getValue($name, ConfigEnum::Condition) ?? [];
    }

    /**
     * @param string $name
     * @return array
     */
    public function getPluginConfig(string $name): array
    {
        return []; //$this->getValue($name, ConfigEnum::PluginConfig) ?? [];
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getNotFound(string $name): ?string
    {
        return $this->getValue($name, ConfigEnum::NotFound);
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getLanguageParentField(string $name): ?string
    {
        return $this->getValue($name, ConfigEnum::LanguageParentField);
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getLanguageField(string $name): ?string
    {
        return $this->getValue($name, ConfigEnum::LanguageField);
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getRecordIdentifier(string $name): ?string
    {
        return $this->getValue($name, ConfigEnum::IdentifierField);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function canLanguageOverlay(string $name): bool
    {
        return !empty($this->getRecordIdentifier($name)) && !empty($this->getLanguageField($name));
    }

    /**
     * @param string $name
     * @param string|ConfigEnum $key
     * @return mixed return value, if not found return NULL
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
