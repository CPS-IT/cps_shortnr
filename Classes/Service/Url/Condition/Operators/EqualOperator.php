<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class EqualOperator implements OperatorInterface
{
    /**
     * @param mixed $fieldConfig
     * @return bool
     */
    public function support(mixed $fieldConfig): bool
    {
        return !is_array($fieldConfig) || array_key_exists('eq', $fieldConfig);
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @return string
     */
    public function process(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent): string
    {
        // normalize to scalar value
        if (is_array($fieldConfig)) {
            $fieldConfig = $fieldConfig['eq'];
        }

        $type = match (gettype($fieldConfig)) {
            'integer' => Connection::PARAM_INT,
            'NULL' => Connection::PARAM_NULL,
            'boolean' => Connection::PARAM_BOOL,
            default => Connection::PARAM_INT_ARRAY
        };
        if ($parent && $parent->hasOperatorTypeInHistory(NotOperator::class)) {
            return $queryBuilder->expr()->neq($fieldName, $queryBuilder->createNamedParameter($fieldConfig, $type));
        }

        return $queryBuilder->expr()->eq($fieldName, $queryBuilder->createNamedParameter($fieldConfig, $type));
    }
}
