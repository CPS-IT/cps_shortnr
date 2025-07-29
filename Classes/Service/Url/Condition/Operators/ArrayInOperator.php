<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;
use Doctrine\DBAL\ArrayParameterType;

class ArrayInOperator implements QueryOperatorInterface
{
    /**
     * @param FieldCondition $fieldCondition
     * @param OperatorContext $context
     * @param OperatorHistory|null $parent
     * @return bool
     */
    public function supports(FieldCondition $fieldCondition, OperatorContext $context, ?OperatorHistory $parent): bool
    {
        $condition = $fieldCondition->getCondition();
        return $context->fieldExists($fieldCondition->getFieldName()) && is_array($condition) && array_is_list($condition);
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @param FieldCondition $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return string
     */
    public function process(FieldCondition $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): string
    {
        $condition = $fieldCondition->getCondition();
        $fieldName = $fieldCondition->getFieldName();
        $type = $this->determineArrayType($condition);
        $qb = $context->getQueryBuilder();
        $placeholder = $qb->createNamedParameter($condition, $type);

        if ($parent && $parent->hasOperatorTypeInHistory(NotOperator::class)) {
            return $qb->expr()->notIn($fieldName, $placeholder);
        }

        return $qb->expr()->in($fieldName, $placeholder);
    }

    /**
     * @param array $values
     * @return int
     */
    private function determineArrayType(array $values): int
    {
        if (empty($values)) {
            return ArrayParameterType::STRING;
        }

        $firstType = gettype($values[0]);
        // Check if all values have the same type
        foreach ($values as $value) {
            if (gettype($value) !== $firstType) {
                return ArrayParameterType::STRING; // Mixed types
            }
        }

        return match ($firstType) {
            'integer' => ArrayParameterType::INTEGER,
            default => ArrayParameterType::STRING
        };
    }
}
