<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Condition\Operators;

use CPSIT\ShortNr\Service\Condition\Operators\DTO\DirectOperatorContext;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\QueryOperatorContext;

class IssetOperator implements QueryOperatorInterface, DirectOperatorInterface
{
    /**
     * @param FieldConditionInterface $fieldCondition
     * @param OperatorContext $context
     * @param OperatorHistory|null $parent
     * @return bool
     */
    public function supports(FieldConditionInterface $fieldCondition, OperatorContext $context, ?OperatorHistory $parent): bool
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
     * @param FieldConditionInterface $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return string
     */
    public function process(FieldConditionInterface $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): string
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

    /**
     * @inheritDoc
     */
    public function directProcess(array $data, FieldConditionInterface $fieldCondition, DirectOperatorContext $context, ?OperatorHistory $parent): ?array
    {
        $isNot = ($parent && $parent->hasOperatorTypeInHistory(NotOperator::class));
        $condition = isset($data[$fieldCondition->getFieldName()]);

        if (($isNot && !$condition) || $condition) {
            return $data;
        }

        return null;
    }
}
