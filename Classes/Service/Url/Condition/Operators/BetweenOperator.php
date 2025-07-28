<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Exception\ShortNrOperatorException;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class BetweenOperator implements QueryOperatorInterface
{
    /**
     * @param mixed $fieldConfig
     * @return bool
     */
    public function supports(mixed $fieldConfig): bool
    {
        return is_array($fieldConfig) && array_key_exists('between', $fieldConfig);
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @return CompositeExpression
     * @throws ShortNrOperatorException
     */
    public function process(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent): CompositeExpression
    {
        $values = $fieldConfig['between'];
        if (!is_array($values) || count($values) !== 2) {
            throw new ShortNrOperatorException('Between operator requires exactly 2 numbers.');
        }

        $value1 = $this->transformToNumber($values[0]);
        $value2 = $this->transformToNumber($values[1]);
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
}
