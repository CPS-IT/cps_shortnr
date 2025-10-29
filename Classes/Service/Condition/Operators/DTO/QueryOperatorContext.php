<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Condition\Operators\DTO;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class QueryOperatorContext extends OperatorContext
{
    /**
     * @param QueryBuilder $queryBuilder
     * @param string $tableName
     * @param array<string, string|int|mixed|array> $condition
     * @param array $existingFields
     */
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        string $tableName,
        array $condition,
        array $existingFields
    )
    {
        parent::__construct($tableName, $condition, $existingFields);
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }
}
