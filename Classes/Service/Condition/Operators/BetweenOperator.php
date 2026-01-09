<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Condition\Operators;

use CPSIT\ShortNr\Service\Condition\Operators\DTO\DirectOperatorContext;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Exception\ShortNrOperatorException;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\QueryOperatorContext;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;

class BetweenOperator implements QueryOperatorInterface, DirectOperatorInterface
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
        return $context->fieldExists($fieldCondition->getFieldName()) && is_array($condition) && array_key_exists(ConfigEnum::ConditionBetween->value, $condition);
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
     * @return CompositeExpression
     * @throws ShortNrOperatorException
     */
    public function process(FieldConditionInterface $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): CompositeExpression
    {
        $condition = $fieldCondition->getCondition();
        $fieldName = $fieldCondition->getFieldName();
        $queryBuilder = $context->getQueryBuilder();

        $values = $condition[ConfigEnum::ConditionBetween->value] ?? null;
        if (!is_array($values) || count($values) !== 2) {
            throw new ShortNrOperatorException('Between operator requires exactly 2 numbers.');
        }

        $value1 = $this->transformToNumber($values[0] ?? null);
        $value2 = $this->transformToNumber($values[1] ?? null);
        if ($value1 === null || $value2 === null) {
            throw new ShortNrOperatorException('Between operator requires exactly 2 numbers. got: ' . $value1. ' and ' . $value2);
        }

        $minValue = min($value1, $value2);
        $maxValue = max($value1, $value2);
        $minParam = $queryBuilder->createNamedParameter($minValue, is_float($minValue) ? Connection::PARAM_STR : Connection::PARAM_INT);
        $maxParam = $queryBuilder->createNamedParameter($maxValue, is_float($maxValue) ? Connection::PARAM_STR : Connection::PARAM_INT);

        if ($parent && $parent->hasOperatorTypeInHistory(NotOperator::class)) {
            return $queryBuilder->expr()->or(
                $queryBuilder->expr()->lt($fieldName, $minParam),
                $queryBuilder->expr()->gt($fieldName, $maxParam)
            );
        }

        return $queryBuilder->expr()->and(
            $queryBuilder->expr()->gte($fieldName, $minParam),
            $queryBuilder->expr()->lte($fieldName, $maxParam)
        );
    }

    /**
     * @param mixed $value
     * @return int|float|null
     */
    private function transformToNumber(mixed $value): int|float|null
    {
        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int)$value;
        }
        if (filter_var($value, FILTER_VALIDATE_FLOAT) !== false) {
            $float = (float)$value;
            // Check if it's actually an integer value
            return (fmod($float, 1) === 0.0) ? (int)$float : $float;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function directProcess(array $data, FieldConditionInterface $fieldCondition, DirectOperatorContext $context, ?OperatorHistory $parent): ?array
    {
        $value = $this->transformToNumber($data[$fieldCondition->getFieldName()] ?? null);
        $values = $condition[ConfigEnum::ConditionBetween->value] ?? null;
        if (!is_array($values) || count($values) !== 2) {
            throw new ShortNrOperatorException('Between operator requires exactly 2 numbers.');
        }

        $value1 = $this->transformToNumber($values[0] ?? null);
        $value2 = $this->transformToNumber($values[1] ?? null);
        if ($value1 === null || $value2 === null) {
            throw new ShortNrOperatorException('Between operator requires exactly 2 numbers. got: ' . $value1. ' and ' . $value2);
        }

        $minValue = min($value1, $value2);
        $maxValue = max($value1, $value2);


        $isNot = ($parent && $parent->hasOperatorTypeInHistory(NotOperator::class));
        $condition = $value >= $minValue && $value <= $maxValue;
        if (($isNot && !$condition) || $condition) {
            return $data;
        }

        return null;
    }
}
