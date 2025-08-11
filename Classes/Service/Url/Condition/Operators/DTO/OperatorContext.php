<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators\DTO;

use CPSIT\ShortNr\Config\DTO\FieldConditionInterface;

abstract class OperatorContext
{
    private array $configCondition = [];
    private array $existingFields = [];
    private string $tableName = '';

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @param string $tableName
     * @return $this
     */
    public function setTableName(string $tableName): static
    {
        $this->tableName = $tableName;
        return $this;
    }

    /**
     * @return array<string, FieldConditionInterface|mixed>
     */
    public function getConfigCondition(): array
    {
        return $this->configCondition;
    }

    /**
     * @param array<string, FieldConditionInterface|mixed> $configCondition
     * @return $this
     */
    public function setConfigCondition(array $configCondition): static
    {
        $this->configCondition = $configCondition;
        return $this;
    }

    /**
     * @return array
     */
    public function getExistingFields(): array
    {
        return array_values($this->existingFields);
    }

    /**
     * @param array $existingFields
     * @return $this
     */
    public function setExistingFields(array $existingFields): static
    {
        $this->existingFields = array_combine($existingFields, $existingFields);;
        return $this;
    }

    /**
     * @param string $fieldName
     * @return FieldConditionInterface|mixed|null
     */
    public function getFieldCondition(string $fieldName): mixed
    {
        return $this->configCondition[$fieldName] ?? null;
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function fieldExists(string $fieldName): bool
    {
        return array_key_exists($fieldName, $this->existingFields);
    }
}
