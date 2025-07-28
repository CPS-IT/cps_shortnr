<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Domain\Repository;

use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Service\Url\Condition\ConditionService;
use Throwable;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class ShortNrRepository
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ConditionService $conditionService,
    )
    {}

    /**
     * @param array $fields
     * @param string $tableName
     * @param array $condition
     * @param string $languageParentFieldName
     * @return array|false
     * @throws ShortNrQueryException
     */
    public function resolveTable(array $fields, string $tableName, array $condition, string $languageParentFieldName = 'l10n_parent'): array|false
    {
        array_push($fields, ...array_keys($condition));
        $fields[] = $languageParentFieldName;

        $qb = $this->getQueryBuilder($tableName);
        $qb->select(...$fields);
        $qb->from($tableName);

        // build where conditions based on the config
        $queryConditions = $this->conditionService->buildQueryCondition($condition, $qb);
        if (empty($queryConditions)) {
            throw new ShortNrQueryException('DB resolve without Conditions are not supported');
        }

        $qb->where(...$queryConditions);
        try {
            return $this->conditionService->postQueryResultFilterCondition($qb->executeQuery()->fetchAssociative(), $condition);
        } catch (Throwable $e) {
            throw new ShortNrQueryException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function getConnection(string $table): Connection
    {
        return $this->connectionPool->getConnectionForTable($table);
    }

    private function getQueryBuilder(string $table): QueryBuilder
    {
        return $this->connectionPool->getQueryBuilderForTable($table);
    }
}
