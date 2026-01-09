<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Condition\Operators\DTO;

abstract class OperatorContext
{
    /**
     * @param string $tableName
     * @param array<string, string|int|mixed|array> $configCondition
     * @param array<string, string> $existingFields
     */
    public function __construct(
        private readonly string $tableName,
        private readonly array $configCondition,
        private readonly array $existingFields
    )
    {}


    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return array
     */
    public function getConfigCondition(): array
    {
        return $this->configCondition;
    }

    /**
     * @param string $field
     * @return int|string|bool|array|null
     */
    public function getConfigConditionByField(string $field): null|int|string|bool|array
    {
        return $this->configCondition[$field] ?? null;
    }

    /**
     * @return string[]
     */
    public function getConfigConditionFields(): array
    {
        return array_keys($this->configCondition);
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function fieldExists(string $fieldName): bool
    {
        return in_array($fieldName, $this->existingFields);
    }

    /**
     * @return array
     */
    public function getExistingFields(): array
    {
        return array_values($this->existingFields);

    }
}
