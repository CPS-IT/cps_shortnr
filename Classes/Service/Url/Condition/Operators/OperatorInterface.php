<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

interface OperatorInterface
{
    /**
     * first comes, first serves logic
     *
     * @param mixed $fieldConfig
     * @return bool return true if the operator can support that fieldConfig otherwise false
     */
    public function support(mixed $fieldConfig): bool;

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @return mixed
     */
    public function process(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent): mixed;
}
