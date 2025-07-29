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
     * @return array|false return one db row result or false
     * @throws ShortNrQueryException
     * @throws ShortNrCacheException
     */
    public function resolveTable(array $fields, string $tableName, array $condition): array|false
    {
        $fields = [...$fields, ...array_keys($condition)];
        $existingValidFields = $this->getValidFields($fields, $tableName);
        if (empty($existingValidFields)) {
            throw new ShortNrQueryException('No Valid Fields Provided');
        }

        $qb = $this->getQueryBuilder($tableName);
        $qb->select(...$existingValidFields);
        $qb->from($tableName);

        // build where conditions based on the config
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
        try {
            return $this->conditionService->postQueryResultFilterCondition(
                (new ResultOperatorContext($qb->executeQuery()->fetchAllAssociative()))
                    ->setTableName($tableName)
                    ->setExistingFields($existingValidFields)
                    ->setConfigCondition($condition)
            );
        } catch (Throwable $e) {
            throw new ShortNrQueryException($e->getMessage() . ' | table: ' . $tableName, $e->getCode(), $e);
        }
    }

    /**
     * @return TreeProcessorResultInterface
     * @throws ShortNrTreeProcessorException
     */
    public function getCachedPageTreeData(): TreeProcessorResultInterface
    {
        try {
            $serializedData = $this->cacheManager->getType3CacheValue(
                'typo3PageTree',
                fn(): ?string => serialize($this->getPageTreeData()),
                ttl: 3600
            );

            if (is_string($serializedData)) {
                return unserialize($serializedData);
            }

            throw new ShortNrTreeProcessorException('Unserialized Page Tree Failed');
        } catch (Throwable $e) {
            throw new ShortNrTreeProcessorException('Could not load Page Tree', previous: $e);
        }
    }

    /**
     * @return TreeProcessorResultInterface
     * @throws Exception
     * @throws ShortNrTreeProcessorException
     */
    public function getPageTreeData(): TreeProcessorResultInterface
    {
        $qb = $this->getQueryBuilder('pages');
        $qb->select('uid', 'pid');
        $qb->from('pages');
        $data =  $qb->executeQuery()->fetchAllAssociative();

        $tpd = new TreeProcessorArrayData('uid', 'pid', $data);
        return $tpd->getResult();
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
