<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Domain\Repository;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Domain\DTO\TreeProcessor\TreeProcessorArrayData;
use CPSIT\ShortNr\Domain\DTO\TreeProcessor\TreeProcessorResultInterface;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Exception\ShortNrTreeProcessorException;
use CPSIT\ShortNr\Service\Url\Condition\ConditionService;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\ResultOperatorContext;
use Doctrine\DBAL\Exception;
use Throwable;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class ShortNrRepository
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ConditionService $conditionService,
        private readonly CacheManager $cacheManager
    )
    {}

    /**
     * @param array $fields
     * @param string $tableName
     * @param array $condition
     * @return array try return exact one db row result
     * @throws ShortNrQueryException
     * @throws ShortNrCacheException
     */
    public function resolveTable(array $fields, string $tableName, array $condition): array
    {
        $existingValidFields = $this->validateAndPrepareFields($fields, $condition, $tableName);
        $queryBuilder = $this->buildQuery($existingValidFields, $tableName, $condition);
        $allResults = $this->executeQuery($queryBuilder, $tableName);
        $filteredResults = $this->filterResults($allResults, $existingValidFields, $tableName, $condition);

        return $filteredResults[array_key_first($filteredResults)] ?? [];
    }

    /**
     * @param array $fields
     * @param array $condition
     * @param string $tableName
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    private function validateAndPrepareFields(array $fields, array $condition, string $tableName): array
    {
        $fields = [...$fields, ...array_keys($condition)];
        $existingValidFields = $this->getValidFields($fields, $tableName);
        if (empty($existingValidFields)) {
            throw new ShortNrQueryException('No Valid Fields Provided');
        }
        return $existingValidFields;
    }

    /**
     * @param array $existingValidFields
     * @param string $tableName
     * @param array $condition
     * @return QueryBuilder
     * @throws ShortNrQueryException
     */
    private function buildQuery(array $existingValidFields, string $tableName, array $condition): QueryBuilder
    {
        $qb = $this->getQueryBuilder($tableName);
        $qb->select(...$existingValidFields);
        $qb->from($tableName);

        $queryConditions = $this->conditionService->buildQueryCondition(
            (new QueryOperatorContext($qb))
                ->setTableName($tableName)
                ->setExistingFields($existingValidFields)
                ->setConfigCondition($condition)
        );
        if (empty($queryConditions)) {
            throw new ShortNrQueryException('DB resolve without Conditions are not supported');
        }

        $qb->where(...$queryConditions);
        return $qb;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $tableName
     * @return array
     * @throws ShortNrQueryException
     */
    private function executeQuery(QueryBuilder $queryBuilder, string $tableName): array
    {
        try {
            return $queryBuilder->executeQuery()->fetchAllAssociative();
        } catch (Throwable $e) {
            throw new ShortNrQueryException($e->getMessage() . ' | table: ' . $tableName, $e->getCode(), $e);
        }
    }

    /**
     * @param array $allResults
     * @param array $existingValidFields
     * @param string $tableName
     * @param array $condition
     * @return array
     */
    private function filterResults(array $allResults, array $existingValidFields, string $tableName, array $condition): array
    {
        return $this->conditionService->postQueryResultFilterCondition(
            (new ResultOperatorContext($allResults))
                ->setTableName($tableName)
                ->setExistingFields($existingValidFields)
                ->setConfigCondition($condition)
        );
    }

    /**
     * @param string $indexField
     * @param string $parentPageField
     * @param string $languageField
     * @param string $languageParentField
     * @return TreeProcessorResultInterface
     * @throws Exception
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     * @throws ShortNrTreeProcessorException
     */
    public function getPageTreeData(string $indexField, string $parentPageField, string $languageField, string $languageParentField): TreeProcessorResultInterface
    {
        $tableName = 'pages';
        $requiredFields = [$indexField, $parentPageField, $languageField, $languageParentField];
        $referencePageFields = $this->getValidFields($requiredFields, $tableName);
        if (count($referencePageFields) !== count($requiredFields)) {
            throw new ShortNrQueryException('Page Reference Fields Provided do not match, pages schema not supported, pages fields in config found: '. implode(',', $requiredFields));
        }

        $qb = $this->getQueryBuilder($tableName);
        $qb->select(...$requiredFields);
        $qb->from($tableName);
        return (new TreeProcessorArrayData(
            primaryKey: $indexField,
            relationKey: $parentPageField,
            languageKey: $languageField,
            languageRelationKey: $languageParentField,
            data: $qb->executeQuery()->fetchAllAssociative()
        ))->getResult();
    }

    /**
     * @param array $fields
     * @param string $tableName
     * @return array
     * @throws ShortNrCacheException
     */
    private function getValidFields(array $fields, string $tableName): array
    {
        $existingFields = $this->getFieldFromTable($tableName);
        return $existingFields ? array_intersect($existingFields, $fields) : [];
    }

    /**
     * @param string $tableName
     * @return array
     * @throws ShortNrCacheException
     */
    private function getFieldFromTable(string $tableName): array
    {
        $list = explode(',', $this->cacheManager->getType3CacheValue(
            cacheKey: 'getFieldFromTable_' . $tableName,
            processBlock: fn(): string => implode(',', array_keys($this->getConnection($tableName)->getSchemaInformation()->introspectTable($tableName)->getColumns())),
            ttl: 0
        ) ?? '');

        if (!is_array($list)) {
            return [];
        }

        return $list;
    }

    /**
     * @param string $table
     * @return Connection
     */
    private function getConnection(string $table): Connection
    {
        return $this->connectionPool->getConnectionForTable($table);
    }

    /**
     * @param string $table
     * @return QueryBuilder
     */
    private function getQueryBuilder(string $table): QueryBuilder
    {
        return $this->connectionPool->getQueryBuilderForTable($table);
    }
}
