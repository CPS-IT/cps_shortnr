<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;

class StringEndsOperator implements QueryOperatorInterface
{
    /**
     * @param FieldCondition $fieldCondition
     * @param OperatorContext $context
     * @param OperatorHistory|null $parent
     * @return bool
     */
    public function supports(FieldCondition $fieldCondition, OperatorContext $context, ?OperatorHistory $parent): bool
    {
        $fieldConfig = $fieldCondition->getCondition();
        return $context->fieldExists($fieldCondition->getFieldName()) && is_array($fieldConfig) && array_key_exists('ends', $fieldConfig);
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
     * @return string|null
     */
    public function process(FieldCondition $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): ?string
    {
        $condition = $fieldCondition->getCondition();
        $fieldName = $fieldCondition->getFieldName();
        $queryBuilder = $context->getQueryBuilder();

        $value = $condition['ends'] ?? null;
        if ($value === null) {
            return null;
        }

        if ($parent && $parent->hasOperatorTypeInHistory(NotOperator::class)) {
            return $queryBuilder->expr()->notLike(
                $fieldName,
                $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($value))
            );
        }

        return $queryBuilder->expr()->like(
            $fieldName,
            $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($value))
        );
    }
}
