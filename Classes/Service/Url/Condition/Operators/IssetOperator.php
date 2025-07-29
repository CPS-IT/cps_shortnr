<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;

class IssetOperator implements QueryOperatorInterface
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
        return $context->fieldExists($fieldCondition->getFieldName()) && is_array($fieldConfig) && array_key_exists(ConfigEnum::ConditionIsset->value, $fieldConfig);
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
        $queryBuilder = $context->getQueryBuilder();

        $isSet = (bool)$condition[ConfigEnum::ConditionIsset->value];

        if ($parent && $parent->hasOperatorTypeInHistory(NotOperator::class)) {
            return $isSet
                ? $queryBuilder->expr()->isNull($fieldName)
                : $queryBuilder->expr()->isNotNull($fieldName);
        }

        return $isSet
            ? $queryBuilder->expr()->isNotNull($fieldName)
            : $queryBuilder->expr()->isNull($fieldName);
    }
}
