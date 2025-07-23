<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

interface WrappingOperatorInterface extends OperatorInterface
{
    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @param callable $nestedCallback
     * @return array|CompositeExpression
     */
    public function wrap(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent, callable $nestedCallback): array|CompositeExpression;
}
