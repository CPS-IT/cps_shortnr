<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Config\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;
use Doctrine\DBAL\ArrayParameterType;

class ArrayInOperator implements QueryOperatorInterface
{
    /**
     * @param FieldConditionInterface $fieldCondition
     * @param OperatorContext $context
     * @param OperatorHistory|null $parent
     * @return bool
     */
    public function supports(FieldConditionInterface $fieldCondition, OperatorContext $context, ?OperatorHistory $parent): bool
    {
        $condition = $fieldCondition->getCondition();
        return
            // field exists in context
            $context->fieldExists($fieldCondition->getFieldName()) &&
            // we only support arrays
            is_array($condition) &&
            (
                // is an indirect IN statement
                    array_is_list($condition)
                ||
                    // or defined via "in"
                    !empty($condition[ConfigEnum::ConditionInArray->value]) &&
                    is_array($condition[ConfigEnum::ConditionInArray->value])
            );
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @param FieldConditionInterface $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return string
     */
    public function process(FieldConditionInterface $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): string
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
