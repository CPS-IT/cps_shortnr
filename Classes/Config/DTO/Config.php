<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\DTO;

use CPSIT\ShortNr\Config\ConfigInterface;

class Config implements ConfigInterface
{
    private array $cache = [];
    private const DEFAULT = '_default';
    private const ENTRYPOINT = 'shortNr';

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
            array_keys($this->data[self::ENTRYPOINT] ?? []),
            fn($name) : bool => ($name !== self::DEFAULT)
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
        return $this->getValue($name, 'regex');
    }

    // Core route properties

    /**
     * @param string $name
     * @return string|null
     */
    public function getPrefix(string $name): ?string
    {
        return $this->getValue($name, 'prefix');
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getType(string $name): ?string
    {
        return $this->getValue($name, 'type');
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getTableName(string $name): ?string
    {
        return $this->getValue($name, 'table');
    }

    /**
     * @param string $name
     * @return array
     */
    public function getCondition(string $name): array
    {
        return $this->getValue($name, 'condition') ?? [];
    }

    /**
     * @param string $name
     * @return array
     */
    public function getRegexGroupMapping(string $name): array
    {
        return $this->getValue($name, 'regexGroupMapping') ?? [];
    }

    /**
     * @param string $name
     * @return array
     */
    public function getPluginConfig(string $name): array
    {
        return $this->getValue($name, 'pluginConfig') ?? [];
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getNotFound(string $name): ?string
    {
        return $this->getValue($name, 'notFound');
    }

    /**
     * @param string $name
     * @param string $key
     * @return mixed
     *
     * @internal
     */
    public function getValue(string $name, string $key): mixed
    {
        return $this->data[self::ENTRYPOINT][$name][$key] ?? $this->data[self::ENTRYPOINT][self::DEFAULT][$key] ?? null;
    }
}
