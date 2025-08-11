<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Domain\Repository;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
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
     * @param array<string, FieldConditionInterface|mixed> $condition
     * @return array return all matching records that are active
     * @throws ShortNrQueryException
     * @throws ShortNrCacheException
     */
    public function resolveTable(array $fields, string $tableName, array $condition): array
    {
        $existingValidFields = $this->validateAndPrepareFields($fields, $condition, $tableName);
        $queryBuilder = $this->buildQuery($existingValidFields, $tableName, $condition);
        $allResults = $this->executeQuery($queryBuilder, $tableName);
        return $this->filterResults($allResults, $existingValidFields, $tableName, $condition);
    }

    /**
     * Returns an array  [sys_language_uid => uid]  for the requested page and
     * all its translations.  Works for any table that uses the “translation pointer”
     * pattern (uid, sys_language_uid, l10n_parent).
     *
     * @throws ShortNrQueryException
     */
    public function resolveCorrectUidWithLanguageUid(
        string $table,
        string $uidField,
        string $languageField,
        string $languageParentField,
        int    $uid
    ): array {

        /* ---------- 1st query: find the base uid ------------------------------ */
        $qb = $this->getQueryBuilder($table);
        $qb->select($uidField, $languageParentField)
            ->from($table)
            ->where(
                $qb->expr()->or(
                    $qb->expr()->eq($uidField,           $uid),
                    $qb->expr()->eq($languageParentField, $uid)
                )
            );

        try {
            $rows = $qb->executeQuery()->fetchAllAssociative();
        } catch (Throwable $e) {
            throw new ShortNrQueryException($e->getMessage(), $e->getCode(), $e);
        }

        if (empty($rows)) {
            return [];
        }

        // Determine the real base uid
        $baseUid = null;
        foreach ($rows as $row) {
            if ((int)($row[$languageParentField] ?? 0) === 0) {
                $baseUid = (int)($row[$uidField] ?? 0);
                break;
            }
        }
        if ($baseUid === null) {
            // row 203 itself is a translation; find its parent
            $baseUid = (int)($rows[0][$languageParentField] ?? 0);
        }

        /* ---------- 2nd query: load all language variants --------------------- */
        $qb = $this->getQueryBuilder($table);
        $qb->select($uidField, $languageField)
            ->from($table)
            ->where(
                $qb->expr()->or(
                    $qb->expr()->eq($uidField,           $baseUid),
                    $qb->expr()->eq($languageParentField, $baseUid)
                )
            );

        try {
            $result = $qb->executeQuery()->fetchAllAssociative();
        } catch (Throwable $e) {
            throw new ShortNrQueryException($e->getMessage(), $e->getCode(), $e);
        }

        /* ---------- build [sys_language_uid => uid] map ----------------------- */
        $list = [];
        foreach ($result as $row) {
            $lang = (int)($row[$languageField] ?? 0);
            $id   = (int)($row[$uidField]     ?? 0);
            $list[$lang] = $id;
        }

        return $list;
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
     * @param array<string, FieldConditionInterface|mixed> $condition
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
     * @param string $tableName
     * @param string $indexField
     * @param string $parentPageField
     * @param string $languageField
     * @param string $languageParentField
     * @return array tree data from table
     * @throws ShortNrQueryException
     */
    public function getPageTreeData(string $tableName, string $indexField, string $parentPageField, string $languageField, string $languageParentField): array
    {
        $requiredFields = [$indexField, $parentPageField, $languageField, $languageParentField];
        try {
            $referencePageFields = $this->getValidFields($requiredFields, $tableName);
            if (count($referencePageFields) !== count($requiredFields)) {
                throw new ShortNrQueryException('Page Reference Fields Provided do not match, \''.$tableName.'\' schema not supported, \''.$tableName.'\' fields in config found: '. implode(',', $requiredFields));
            }
        } catch (ShortNrCacheException) {
            // let it through, we catch the error later
        }


        $qb = $this->getQueryBuilder($tableName);
        $qb->select(...$requiredFields);
        $qb->from($tableName);

        try {
            return $qb->executeQuery()->fetchAllAssociative();
        } catch (Throwable $e) {
            throw new ShortNrQueryException($e->getMessage(), $e->getCode(), $e);
        }
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
