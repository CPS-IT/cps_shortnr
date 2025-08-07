<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition;

use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Url\Condition\DTO\ConfigMatchCandidate;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\ResultOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\OperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\QueryOperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\ResultOperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\WrappingOperatorInterface;
use CPSIT\ShortNr\Service\Url\Processor\NotFoundProcessor;
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
        private readonly iterable $resultOperators,
        private readonly PlaceholderResolver $placeholderResolver,
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
     * Filter Data Direct from Results, returns filtered results array
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
        $filteredResults = [];

        foreach ($results as $result) {
            $skipFilterResultFlag = false;
            foreach ($conditions as $fieldName => $condition) {
                if (
                    $this->processPostResultFieldConfig(
                        $result,
                        new FieldCondition($fieldName, $condition),
                        $context,
                        null
                    ) === null
                ) {
                    // one result filter
                    $skipFilterResultFlag = true;
                    break;
                }
            }

            if (!$skipFilterResultFlag) {
                $filteredResults[] = $result;
            }
        }

        return $filteredResults;
    }

    /**
     * Return all config names and matches that successfully matched a regex
     *
     * @param string $uri
     * @param ConfigInterface $config
     * @return Generator<ConfigMatchCandidate>
     */
    public function findAllMatchConfigCandidates(string $uri, ConfigInterface $config): Generator
    {
        return $this->matchGenerator($uri, $config);
    }

    /**
     * merged placeholder into condition array
     *
     * @param ConfigMatchCandidate $candidate
     * @param ConfigItemInterface $config
     * @return array
     */
    public function resolveConditionToArray(ConfigMatchCandidate $candidate, ConfigItemInterface $config): array
    {
        // replace all matchPlaceholder with the candidate match result
        $conditions =  $this->placeholderResolver->replace($config->getCondition(), $candidate);
        // remove all orphan matchPlaceholder that are still exists.
        return $this->placeholderResolver->strip($conditions, $candidate);
    }

    /**
     * @param ConfigMatchCandidate $candidate
     * @param ConfigInterface $config
     * @return ConfigItemInterface|null
     */
    public function getConfigItem(ConfigMatchCandidate $candidate, ConfigInterface $config): ?ConfigItemInterface
    {
        foreach ($candidate->getNames() as $name) {
            // if no config item exists for that name ... skip
            try {
                $configItem = $config->getConfigItem($name);
            } catch (ShortNrConfigException) {
                continue;
            }

            $prefix = $candidate->getValueFromMatchesViaMatchGroupString($configItem->getPrefixMatch());
            if (strtolower($configItem->getPrefix()) === strtolower($prefix)) {
                return $configItem;
            }
        }

        return null;
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
     * null means no match / result back means match
     *
     * @param array $result
     * @param FieldCondition $fieldCondition
     * @param ResultOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return array|null
     */
    private function processPostResultFieldConfig(array $result, FieldCondition $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent): ?array
    {
        $operator = $this->findPostResultOperator($fieldCondition, $context, $parent);
        // no operator found, mark as matched
        if ($operator === null) {
            return $result;
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
     * Generator that yields matches for each successful regex
     *
     * @param string $uri
     * @param ConfigInterface $config
     * @return Generator<ConfigMatchCandidate>
     */
    private function matchGenerator(string $uri, ConfigInterface $config): Generator
    {
        // one regex can be used for many "Config-Item-Names"
        foreach ($config->getUniqueRegexConfigNameGroup() as $regex => $names) {
            // match that regex against our potential shortNr URI
            $regexMatches = $this->matchRegex($uri, $regex);
            if ($regexMatches !== null) {
                yield new ConfigMatchCandidate($uri, $names, $regexMatches);
            }
        }
    }

    /**
     * gives the matches of the regex check
     *
     * @param string $shortNr
     * @param string $regex
     * @return array|null
     */
    private function matchRegex(string $shortNr, string $regex): ?array
    {
        $cacheKey = $shortNr.'::'.$regex;
        if (isset($this->cache['match'][$cacheKey])) {
            return $this->cache['match'][$cacheKey];
        }

        $matches = [];
        if (preg_match($regex, $shortNr, $matches, PREG_OFFSET_CAPTURE)) {
            return $this->cache['match'][$cacheKey] = $matches;
        }

        return $this->cache['match'][$cacheKey] = null;
    }
}
