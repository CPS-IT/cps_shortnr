<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class AndOperator implements WrappingOperatorInterface
{
    /**
     * @param mixed $fieldConfig
     * @return bool
     */
    public function support(mixed $fieldConfig): bool
    {
        return is_array($fieldConfig) && count($fieldConfig) > 1 && !array_is_list($fieldConfig);
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @return null
     */
    public function process(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent): null
    {
        return null;
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @param callable $nestedCallback
     * @return CompositeExpression
     */
    public function wrap(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent, callable $nestedCallback): CompositeExpression
    {
        $andConditions = [];
        foreach ($fieldConfig as $operatorName => $fieldConfigSegment) {
            $andConditions[] = $nestedCallback($fieldName, [$operatorName => $fieldConfigSegment], $queryBuilder, new OperatorHistory($parent, $this));
        }

        return $queryBuilder->expr()->and(
            ...$andConditions
        );
    }
}
