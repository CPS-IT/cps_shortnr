<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition;

use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\ResultOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\OperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\QueryOperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\ResultOperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\WrappingOperatorInterface;
use Generator;

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
     * @param QueryOperatorContext $context
     * @return array
     */
    public function buildQueryCondition(QueryOperatorContext $context): array
    {
        $dbConditions = [];
        foreach ($context->getConfigCondition() as $fieldName => $condition) {
            $dbCondition = $this->processFieldConfig(
                new FieldCondition($fieldName, $condition),
                $context,
                null
            );
            if (!empty($dbCondition)) {
                $dbConditions[] = $dbCondition;
            }
        }

        return $dbConditions;
    }

    /**
     * Filter Data Direct from Results
     *
     * @param ResultOperatorContext $context
     * @return array
     */
    public function postQueryResultFilterCondition(ResultOperatorContext $context): array
    {
        $results = $context->getResults();
        if (empty($results)) {
            return [];
        }

        $conditions = $context->getConfigCondition();
        foreach ($results as $result) {
            foreach ($conditions as $fieldName => $condition) {
                if (
                    $filteredResults = $this->processPostResultFieldConfig(
                        $result,
                        new FieldCondition($fieldName, $condition),
                        $context,
                        null
                    )
                ) {
                    return $filteredResults;
                }
            }
        }

        return $results[array_key_first($results)];
    }

    /**
     * @param FieldCondition $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return mixed
     */
    private function processFieldConfig(FieldCondition $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): mixed
    {
        $operator = $this->findQueryBuilderOperator($fieldCondition, $context, $parent);
        if ($operator === null) {
            return null;
        }

        if ($operator instanceof WrappingOperatorInterface) {
            // do magic to unwrap
            return $operator->wrap($fieldCondition, $context, $parent, fn(FieldCondition $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): mixed => $this->processFieldConfig($fieldCondition, $context, $parent));
        }

        return $operator->process($fieldCondition, $context, $parent);
    }

    /**
     * @param array $result
     * @param FieldCondition $fieldCondition
     * @param ResultOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return array|null
     */
    private function processPostResultFieldConfig(array $result, FieldCondition $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent): ?array
    {
        $operator = $this->findPostResultOperator($fieldCondition, $context, $parent);
        if ($operator === null) {
            return null;
        }

        if ($operator instanceof WrappingOperatorInterface) {
            // do magic to unwrap
            return $operator->postResultWrap($result, $fieldCondition, $context, $parent, fn(array $result, FieldCondition $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent): ?array => $this->processPostResultFieldConfig($result, $fieldCondition, $context, $parent));
        }

        return $operator->postResultProcess($result, $fieldCondition, $context, $parent);
    }

    /**
     * @param FieldCondition $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return QueryOperatorInterface|null
     */
    private function findQueryBuilderOperator(FieldCondition $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): ?QueryOperatorInterface
    {
        $operator = $this->findOperator($this->queryOperators, $fieldCondition, $context, $parent);
        if ($operator instanceof QueryOperatorInterface) {
            return $operator;
        }

        return null;
    }

    /**
     * @param FieldCondition $fieldCondition
     * @param ResultOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return ResultOperatorInterface|null
     */
    private function findPostResultOperator(FieldCondition $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent): ?ResultOperatorInterface
    {
        $operator = $this->findOperator($this->resultOperators, $fieldCondition, $context, $parent);
        if ($operator instanceof ResultOperatorInterface) {
            return $operator;
        }

        return null;
    }

    /**
     * @param iterable<OperatorInterface> $operators
     * @param FieldCondition $fieldCondition
     * @param OperatorContext $context
     * @param OperatorHistory|null $parent
     * @return OperatorInterface|null
     */
    private function findOperator(iterable $operators, FieldCondition $fieldCondition, OperatorContext $context, ?OperatorHistory $parent): ?OperatorInterface
    {
        $operatorList = [];
        foreach ($operators as $operator) {
            if ($operator->supports($fieldCondition, $context, $parent)) {
                $operatorList[$operator->getPriority()] ??= $operator;
            }
        }

        if (empty($operatorList)) {
            return null;
        }

        ksort($operatorList, SORT_NUMERIC);
        return $operatorList[array_key_last($operatorList)] ?? null;
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
