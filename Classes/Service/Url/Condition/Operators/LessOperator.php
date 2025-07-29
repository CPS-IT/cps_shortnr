<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;
use TYPO3\CMS\Core\Database\Connection;

class LessOperator implements QueryOperatorInterface
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
        return $context->fieldExists($fieldCondition->getFieldName()) && is_array($fieldConfig) && (array_key_exists(ConfigEnum::ConditionLessThan->value, $fieldConfig) || array_key_exists(ConfigEnum::ConditionLessThanEqual->value, $fieldConfig));
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

        $isLte = array_key_exists(ConfigEnum::ConditionLessThanEqual->value, $condition);
        $value = $isLte ? $condition[ConfigEnum::ConditionLessThanEqual->value] : $condition[ConfigEnum::ConditionLessThan->value];

        $type = match (gettype($value)) {
            'integer' => Connection::PARAM_INT,
            'NULL' => Connection::PARAM_NULL,
            'boolean' => Connection::PARAM_BOOL,
            default => Connection::PARAM_STR
        };

        $param = $queryBuilder->createNamedParameter($value, $type);

        if ($parent && $parent->hasOperatorTypeInHistory(NotOperator::class)) {
            return $isLte
                ? $queryBuilder->expr()->gt($fieldName, $param)
                : $queryBuilder->expr()->gte($fieldName, $param);
        }

        return $isLte
            ? $queryBuilder->expr()->lte($fieldName, $param)
            : $queryBuilder->expr()->lt($fieldName, $param);
    }
}
