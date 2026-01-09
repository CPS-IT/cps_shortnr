<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Condition\Operators\DTO;

interface FieldConditionInterface
{
    /**
     * @return string
     */
    public function getFieldName(): string;

    /**
     * @return mixed
     */
    public function getCondition(): mixed;
}
