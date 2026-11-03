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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;

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
        $this->restrictToLive($qb);
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
     * can see now; several visible rows in one language: the lowest uid.
     * A missing key for the base record's language means the base record is not visible.
     *
     * @param array<string, list<int>> $excludeValues [field => values] rows to drop, e.g. non-renderable doktypes
     * @return array<int, int>
     * @throws ShortNrQueryException
     * @see findVisibleLanguageFamily() for the base uid and all visible rows
     */
    public function findVisibleLanguageVariants(
        string $table,
        string $uidField,
        string $languageField,
        string $languageParentField,
        int    $uid,
        array  $excludeValues = []
    ): array {
        $list = [];
        foreach ($this->findVisibleLanguageFamily($table, $uidField, $languageField, $languageParentField, $uid, $excludeValues)['rows'] as $row) {
            $list[$row['language']] ??= $row['uid'];
        }

        return $list;
    }

    /**
     * The language family of $uid (its base record and all translations pointing to it), limited to
     * what an anonymous visitor can see now. Works for any table with the translation pointer pattern.
     *
     * - The base uid is resolved without visibility: a short number may carry the uid of a hidden
     *   translation and must still find its base record.
     * - The base record is the row without language parent. Usually language 0, but a record may exist
     *   only in another language (l10n_parent = 0, "free mode"), then that row is the base.
     * - Visible rows honour deleted, hidden, starttime and endtime from TCA enablecolumns (core default
     *   restrictions, time from $GLOBALS['SIM_ACCESS_TIME']) and the live workspace only.
     *
     * @param array<string, list<int>> $excludeValues [field => values] rows to drop, e.g. non-renderable doktypes
     * @return array{baseUid: int|null, rows: list<array{uid: int, language: int}>} rows ordered by uid
     * @throws ShortNrQueryException
     */
    public function findVisibleLanguageFamily(
        string $table,
        string $uidField,
        string $languageField,
        string $languageParentField,
        int    $uid,
        array  $excludeValues = []
    ): array {
        $baseUid = $this->resolveBaseUid($table, $uidField, $languageParentField, $uid);
        if ($baseUid === null) {
            return ['baseUid' => null, 'rows' => []];
        }

        $rows = [];
        foreach ($this->fetchLanguageFamily($table, [$uidField, $languageField], $uidField, $languageParentField, $baseUid, true, $excludeValues) as $row) {
            $rows[] = ['uid' => (int)($row[$uidField] ?? 0), 'language' => (int)($row[$languageField] ?? 0)];
        }

        return ['baseUid' => $baseUid, 'rows' => $rows];
    }

    /**
     * The earliest starttime or endtime after $now in the language family of $uid (any visibility):
     * the moment a decode answer for it can change without anybody saving a record.
     *
     * @return int|null timestamp, null if nothing is scheduled (or the table has no such enablecolumns)
     * @throws ShortNrQueryException
     */
    public function findNextVisibilityChange(string $table, string $uidField, string $languageParentField, int $uid, int $now): ?int
    {
        $enableColumns = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns'] ?? [];
        $timeFields = array_values(array_filter([$enableColumns['starttime'] ?? null, $enableColumns['endtime'] ?? null]));
        if (empty($timeFields)) {
            return null;
        }

        $baseUid = $this->resolveBaseUid($table, $uidField, $languageParentField, $uid);
        if ($baseUid === null) {
            return null;
        }

        $next = null;
        foreach ($this->fetchLanguageFamily($table, $timeFields, $uidField, $languageParentField, $baseUid, false) as $row) {
            foreach ($timeFields as $field) {
                $time = (int)($row[$field] ?? 0);
                if ($time > $now && ($next === null || $time < $next)) {
                    $next = $time;
                }
            }
        }

        return $next;
    }

    /**
     * Default language uid of $uid, ignoring visibility: a short number may carry the uid of a
     * hidden translation.
     *
     * @throws ShortNrQueryException
     */
    private function resolveBaseUid(string $table, string $uidField, string $languageParentField, int $uid): ?int
    {
        $rows = $this->fetchLanguageFamily($table, [$uidField, $languageParentField], $uidField, $languageParentField, $uid, false);
        if (empty($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if ((int)($row[$languageParentField] ?? 0) === 0) {
                return (int)($row[$uidField] ?? 0);
            }
        }

        // the requested uid is a translation, follow its parent
        return (int)($rows[0][$languageParentField] ?? 0);
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
        if ($visibleOnly) {
            $this->restrictToLive($qb);
        } else {
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
     * Visible to an anonymous visitor: on top of the default restrictions (deleted, hidden, starttime,
     * endtime) only live rows, no workspace versions (no-op for tables without versioningWS).
     */
    private function restrictToLive(QueryBuilder $qb): void
    {
        $qb->getRestrictions()->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, 0));
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
