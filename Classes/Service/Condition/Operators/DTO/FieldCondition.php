<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Condition\Operators\DTO;

class FieldCondition implements FieldConditionInterface
{
    public function __construct(
        private readonly string $fieldName,
        private readonly mixed $condition
    )
    {}

    /**
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * @return mixed
     */
    public function getCondition(): mixed
    {
        return $this->condition;
    }
}
