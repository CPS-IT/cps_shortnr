<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class IssetOperator implements OperatorInterface
{
    /**
     * @param mixed $fieldConfig
     * @return bool
     */
    public function support(mixed $fieldConfig): bool
    {
        return is_array($fieldConfig) && array_key_exists('isset', $fieldConfig);
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
        $isSet = (bool)$fieldConfig['isset'];

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
