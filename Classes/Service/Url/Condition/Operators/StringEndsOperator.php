<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class StringEndsOperator implements OperatorInterface
{
    /**
     * @param mixed $fieldConfig
     * @return bool
     */
    public function support(mixed $fieldConfig): bool
    {
        return is_array($fieldConfig) && array_key_exists('ends', $fieldConfig);
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
        $value = $fieldConfig['ends'];

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
