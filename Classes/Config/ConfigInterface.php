<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config;

interface ConfigInterface
{
    /**
     * @return string[]
     */
    public function getConfigNames(): array;

    /**
     * gather all regex of all names and create a regex per name list.
     *
     * @return array<string, array>
     */
    public function getUniqueRegexConfigNameGroup(): array;

    /**
     * @param string $name
     * @return string|null return the regex of the given type fall bock on _default if not set.
     *
     * if no regex at all configured NULL is returned
     */
    public function getRegex(string $name): ?string;

    // Core route properties

    /**
     * @param string $name
     * @return string|null
     */
    public function getPrefix(string $name): ?string;

    /**
     * @param string $name
     * @return string|null
     */
    public function getType(string $name): ?string;

    /**
     * @param string $name
     * @return string|null
     */
    public function getTableName(string $name): ?string;

    /**
     * @param string $name
     * @return array
     */
    public function getCondition(string $name): array;

    /**
     * @param string $name
     * @return array
     */
    public function getRegexGroupMapping(string $name): array;

    /**
     * @param string $name
     * @return array
     */
    public function getPluginConfig(string $name): array;

    /**
     * @param string $name
     * @return string|null
     */
    public function getNotFound(string $name): ?string;

    /**
     * @param string $name
     * @param string $key
     * @return mixed
     *
     * @internal
     */
    public function getValue(string $name, string $key): mixed;
}
