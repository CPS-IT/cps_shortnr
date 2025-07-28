<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition;

use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\QueryOperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\ResultOperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\WrappingOperatorInterface;
use Generator;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class ConditionService
{
    private array $cache = [];

    /**
     * @param iterable<QueryOperatorInterface> $queryOperators
     * @param iterable<ResultOperatorInterface> $resultOperators
     */
    public function __construct(
        private readonly iterable $queryOperators,
        private readonly iterable $resultOperators
    )
    {}

    /**
     * creates pre query where conditions
     *
     * @param array $conditionConfig
     * @param QueryBuilder $queryBuilder
     * @return array
     */
    public function buildQueryCondition(array $conditionConfig, QueryBuilder $queryBuilder): array
    {
        $dbConditions = [];
        foreach ($conditionConfig as $fieldName => $fieldConfig) {
            $dbCondition = $this->processFieldConfig($fieldName, $fieldConfig, $queryBuilder, null);
            if (!empty($dbCondition)) {
                $dbConditions[] = $dbCondition;
            }
        }

        return $dbConditions;
    }

    /**
     * Filter Data Direct from Results
     *
     * @param array<array> $results
     * @param array $conditionConfig
     * @return array
     */
    public function postQueryResultFilterCondition(array $results, array $conditionConfig): array
    {
        $filteredResults = [];
        foreach ($results as $result) {
            foreach ($conditionConfig as $fieldName => $fieldConfig) {
                $filteredResults[] = $this->processPostResultFieldConfig($fieldName, $fieldConfig, $result, null);
            }
        }

        return array_filter($filteredResults);
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param QueryBuilder $queryBuilder
     * @param OperatorHistoryInterface|null $parent
     * @return mixed
     */
    private function processFieldConfig(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent): mixed
    {
        $operator = $this->findQueryBuilderOperator($fieldConfig);
        if ($operator === null) {
            return null;
        }

        if ($operator instanceof WrappingOperatorInterface) {
            // do magic to unwrap
            return $operator->wrap($fieldName, $fieldConfig, $queryBuilder, $parent, fn(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent): mixed => $this->processFieldConfig($fieldName, $fieldConfig, $queryBuilder, $parent));
        }

        return $operator->process($fieldName, $fieldConfig, $queryBuilder, $parent);
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param array $result
     * @param OperatorHistoryInterface|null $parent
     * @return array|null
     */
    private function processPostResultFieldConfig(string $fieldName, mixed $fieldConfig, array $result, ?OperatorHistoryInterface $parent): ?array
    {
        $operator = $this->findPostResultOperator($fieldConfig);
        if ($operator === null) {
            return null;
        }

        if ($operator instanceof WrappingOperatorInterface) {
            // do magic to unwrap
            return $operator->postResultWrap($fieldName, $fieldConfig, $result, $parent, fn(string $fieldName, mixed $fieldConfig, array $result, ?OperatorHistoryInterface $parent): ?array => $this->processPostResultFieldConfig($fieldName, $fieldConfig, $result, $parent));
        }

        return $operator->postResultProcess($fieldName, $fieldConfig, $result, $parent);
    }

    /**
     * @param mixed $fieldConfig
     * @return QueryOperatorInterface|null
     */
    private function findQueryBuilderOperator(mixed $fieldConfig): ?QueryOperatorInterface
    {
        foreach ($this->queryOperators as $operator) {
            if ($operator->supports($fieldConfig)) {
                return $operator;
            }
        }

        return null;
    }

    /**
     * @param mixed $fieldConfig
     * @return ResultOperatorInterface|null
     */
    private function findPostResultOperator(mixed $fieldConfig): ?ResultOperatorInterface
    {
        foreach ($this->resultOperators as $operator) {
            if ($operator->supports($fieldConfig)) {
                return $operator;
            }
        }

        return null;
    }

    /**
     * Fast check if any given regex matches the uri
     *
     * @param string $uri
     * @param ConfigInterface $config
     * @return bool
     */
    public function matchAny(string $uri, ConfigInterface $config): bool
    {
        foreach ($this->matchGenerator($uri, $config) as $match) {
            return true; // First match found
        }
        return false;
    }

    /**
     * Return all config names and matches that successfully matched a regex
     *
     * @param string $uri
     * @param ConfigInterface $config
     * @return Generator
     */
    public function findAllMatchConfigCandidates(string $uri, ConfigInterface $config): Generator
    {
        return $this->matchGenerator($uri, $config);
    }

    /**
     * Generator that yields matches for each successful regex
     *
     * @param string $uri
     * @param ConfigInterface $config
     * @return Generator<array>
     */
    private function matchGenerator(string $uri, ConfigInterface $config): Generator
    {
        foreach ($config->getUniqueRegexConfigNameGroup() as $regex => $names) {
            $regexMatches = $this->matchRegex($uri, $regex);
            if ($regexMatches !== null) {
                yield [
                    'matches' => $regexMatches,
                    'names' => $names,
                ];
            }
        }
    }

    /**
     * gives the matches of the regex check
     *
     * @param string $uri
     * @param string $regex
     * @return array|null
     */
    private function matchRegex(string $uri, string $regex): ?array
    {
        $cacheKey = $uri.'::'.$regex;
        if (isset($this->cache['match'][$cacheKey])) {
            return $this->cache['match'][$cacheKey];
        }

        $matches = [];
        if (preg_match($regex, $uri, $matches, PREG_OFFSET_CAPTURE)) {
            return $this->cache['match'][$cacheKey] = $matches;
        }

        return $this->cache['match'][$cacheKey] = null;
    }
}
