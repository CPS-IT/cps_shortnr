<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class GreaterOperator implements QueryOperatorInterface
{
    /**
     * @param mixed $fieldConfig
     * @return bool
     */
    public function supports(mixed $fieldConfig): bool
    {
        return is_array($fieldConfig) && (array_key_exists('gt', $fieldConfig) || array_key_exists('gte', $fieldConfig));
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
        $isGte = array_key_exists('gte', $fieldConfig);
        $value = $isGte ? $fieldConfig['gte'] : $fieldConfig['gt'];

        $type = match (gettype($value)) {
            'integer' => Connection::PARAM_INT,
            'NULL' => Connection::PARAM_NULL,
            'boolean' => Connection::PARAM_BOOL,
            default => Connection::PARAM_STR
        };

        $param = $queryBuilder->createNamedParameter($value, $type);

        if ($parent && $parent->hasOperatorTypeInHistory(NotOperator::class)) {
            return $isGte
                ? $queryBuilder->expr()->lt($fieldName, $param)
                : $queryBuilder->expr()->lte($fieldName, $param);
        }

        return $isGte
            ? $queryBuilder->expr()->gte($fieldName, $param)
            : $queryBuilder->expr()->gt($fieldName, $param);
    }
}
