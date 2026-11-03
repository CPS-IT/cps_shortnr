<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Domain\Repository;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Service\Condition\ConditionService;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\QueryOperatorContext;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\ResultOperatorContext;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Throwable;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;

class ShortNrRepository
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly CacheManager $cacheManager,
        private readonly ConditionService $conditionService
    )
    {}

    /**
     * Decode lookup: the records matching the short number and the config conditions that an
     * anonymous visitor can see now (deleted, hidden, starttime, endtime from TCA enablecolumns,
     * via the core default restrictions). Decode runs before backend authentication, so there is
     * no preview context to honour; see findVisibleLanguageVariants().
     *
     * @param array $fields identifier field first
     * @param string $tableName
     * @param array<string, string|int|mixed> $condition
     * @param array<string, list<int>> $excludeValues [field => values] rows to drop, e.g. non-renderable doktypes
     * @return array return all matching records that are visible
     * @throws ShortNrQueryException
     * @throws ShortNrCacheException
     */
    public function resolveTable(array $fields, string $tableName, array $condition, array $excludeValues = []): array
    {
        // normalize Conditions
        $existingValidFields = $this->validateAndPrepareFields($fields, $condition, $tableName);
        if (empty($existingValidFields)) {
            return [];
        }

        $qb = $this->getQueryBuilder($tableName);
        $qb->select(...$existingValidFields);
        $qb->from($tableName);

        $queryConditions = $this->conditionService->buildQueryCondition(new QueryOperatorContext($qb, $tableName, $condition, $existingValidFields));
        if (empty($queryConditions)) {
            throw new ShortNrQueryException('table: ' . $tableName.', must have at least one VALID condition');
        } else {
            $qb->where(...$queryConditions, ...$this->buildExclusions($qb, $excludeValues));
        }
        // callers pass the identifier field first: several matches -> lowest uid first, deterministic
        if (in_array($fields[0] ?? null, $existingValidFields, true)) {
            $qb->orderBy($fields[0], 'ASC');
        }

        try {
            return $this->conditionService->postQueryResultFilterCondition(new ResultOperatorContext($qb->executeQuery()->fetchAllAssociative(), $tableName, $condition, $existingValidFields));
        } catch (Throwable $e) {
            throw new ShortNrQueryException($e->getMessage() . ' | table: ' . $tableName, $e->getCode(), $e);
        }
    }

    /**
     * @param array $missingFields
     * @param string $uidField
     * @param string $languageField
     * @param string $parentField
     * @param int $uid
     * @param int $languageUid
     * @param string $tableName
     * @return array|null
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    public function loadMissingFields(array $missingFields, string $uidField, string $languageField, string $parentField, int $uid, int $languageUid, string $tableName): ?array
    {
        // normalize Conditions
        $existingValidFields = $this->validateAndPrepareFields([$uidField, $languageField, $parentField ,...$missingFields], [], $tableName);
        if (empty($existingValidFields)) {
            return [];
        }

        $qb = $this->getQueryBuilder($tableName);
        $qb->getRestrictions()
            ->removeByType(HiddenRestriction::class)
            ->removeByType(StartTimeRestriction::class)
            ->removeByType(EndTimeRestriction::class);
        $qb->select(...$existingValidFields);
        $qb->from($tableName);
        $qb->where(
            $qb->expr()->or(
                $qb->expr()->eq($uidField, $uidParam = $qb->createNamedParameter($uid, ParameterType::INTEGER)),
                $qb->expr()->eq($parentField, $uidParam),
            ),
            $qb->expr()->in($languageField, $qb->createNamedParameter([$languageUid, -1], ArrayParameterType::INTEGER))
        );

        try {
            $result = $qb->executeQuery()->fetchAssociative();
            if (is_array($result) && !empty($result)) {
                return $result;
            }

            return null;

        } catch (Throwable $e) {
            throw new ShortNrQueryException($e->getMessage() . ' | table: ' . $tableName, $e->getCode(), $e);
        }
    }

    /**
     * [languageId => uid] of a record and its translations, limited to what an anonymous visitor
     * can see now. Works for any table with the translation pointer pattern (uid, language, parent).
     *
     * - The base uid is resolved without visibility: a short number may carry the uid of a hidden
     *   translation and must still find its default language record.
     * - The variants honour deleted, hidden, starttime and endtime from TCA enablecolumns (core default
     *   restrictions, time from $GLOBALS['SIM_ACCESS_TIME']).
     * - Several visible rows in one language (left over drafts, see DataHandler::localize() duplicate
     *   check being pid-restricted): the lowest uid wins, the same row the core overlay picks in practice.
     * - The default language record appears under its own language (0, or -1 for "all languages");
     *   a missing 0/-1 key means the default record is not visible.
     *
     * @param array<string, list<int>> $excludeValues [field => values] rows to drop, e.g. non-renderable doktypes
     * @return array<int, int>
     * @throws ShortNrQueryException
     */
    public function findVisibleLanguageVariants(
        string $table,
        string $uidField,
        string $languageField,
        string $languageParentField,
        int    $uid,
        array  $excludeValues = []
    ): array {
        $rows = $this->fetchLanguageFamily($table, [$uidField, $languageParentField], $uidField, $languageParentField, $uid, false);
        if (empty($rows)) {
            return [];
        }

        $baseUid = null;
        foreach ($rows as $row) {
            if ((int)($row[$languageParentField] ?? 0) === 0) {
                $baseUid = (int)($row[$uidField] ?? 0);
                break;
            }
        }
        // the requested uid is a translation, follow its parent
        $baseUid ??= (int)($rows[0][$languageParentField] ?? 0);

        $variants = $this->fetchLanguageFamily($table, [$uidField, $languageField], $uidField, $languageParentField, $baseUid, true, $excludeValues);
        $list = [];
        foreach ($variants as $row) {
            $list[(int)($row[$languageField] ?? 0)] ??= (int)($row[$uidField] ?? 0);
        }

        return $list;
    }

    /**
     * The record $uid and all rows pointing to it as language parent, ordered by uid.
     *
     * @param array<string, list<int>> $excludeValues
     * @throws ShortNrQueryException
     */
    private function fetchLanguageFamily(string $table, array $fields, string $uidField, string $languageParentField, int $uid, bool $visibleOnly, array $excludeValues = []): array
    {
        $qb = $this->getQueryBuilder($table);
        if (!$visibleOnly) {
            // only respect deleted
            $qb->getRestrictions()
                ->removeByType(HiddenRestriction::class)
                ->removeByType(StartTimeRestriction::class)
                ->removeByType(EndTimeRestriction::class);
        }
        $qb->select(...$fields)
            ->from($table)
            ->where(
                $qb->expr()->or(
                    $qb->expr()->eq($uidField, $uidParam = $qb->createNamedParameter($uid, ParameterType::INTEGER)),
                    $qb->expr()->eq($languageParentField, $uidParam)
                ),
                ...$this->buildExclusions($qb, $excludeValues)
            )
            ->orderBy($uidField, 'ASC');

        try {
            return $qb->executeQuery()->fetchAllAssociative();
        } catch (Throwable $e) {
            throw new ShortNrQueryException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, list<int>> $excludeValues [field => values]
     * @return list<string>
     */
    private function buildExclusions(QueryBuilder $qb, array $excludeValues): array
    {
        $constraints = [];
        foreach ($excludeValues as $field => $values) {
            if (!empty($values)) {
                $constraints[] = $qb->expr()->notIn($field, $qb->createNamedParameter(array_map('intval', $values), ArrayParameterType::INTEGER));
            }
        }

        return $constraints;
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
        $fields = array_filter($fields);
        $fields = [...$fields, ...array_keys($condition)];
        $existingValidFields = $this->getValidFields($fields, $tableName);
        if (empty($existingValidFields)) {
            throw new ShortNrQueryException('No Valid Fields Provided');
        }
        return $existingValidFields;
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
    public function getDbTreeData(string $tableName, string $indexField, string $parentPageField, string $languageField, string $languageParentField): array
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
        // only respect deleted
        $qb->getRestrictions()
            ->removeByType(HiddenRestriction::class)
            ->removeByType(StartTimeRestriction::class)
            ->removeByType(EndTimeRestriction::class);

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
        $list = $this->cacheManager->getType3CacheValue(
            cacheKey: 'getFieldFromTable_' . $tableName,
            processBlock: fn(): array => $this->fetchFieldsFromConnection($tableName),
            ttl: 0,
            tags: ['meta', 'database', 'all', 'table', $tableName]
        ) ?? [];

        if (!is_array($list)) {
            return [];
        }

        return $list;
    }

    private function fetchFieldsFromConnection(string $tableName): array
    {
        $fieldList = [];
        foreach ($this->getConnection($tableName)->getSchemaInformation()->listTableColumnNames($tableName) as $column) {
            $fieldList[] = $column;
        }

        return $fieldList;
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
