<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators\DTO;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class QueryOperatorContext extends OperatorContext
{
    /**
     * @param QueryBuilder $queryBuilder
     */
    public function __construct(
        private readonly QueryBuilder $queryBuilder
    )
    {}

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }
}
