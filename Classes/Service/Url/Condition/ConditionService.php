<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition;

use CPSIT\ShortNr\Config\DTO\FieldCondition;
use CPSIT\ShortNr\Config\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\ResultOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\OperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\QueryOperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\ResultOperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\WrappingOperatorInterface;

class ConditionService
{
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
                $this->generateFieldCondition($fieldName, $condition),
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
                        $this->generateFieldCondition($fieldName, $condition),
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
     * @param FieldConditionInterface $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return mixed
     */
    private function processFieldConfig(FieldConditionInterface $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): mixed
    {
        $operator = $this->findQueryBuilderOperator($fieldCondition, $context, $parent);
        if ($operator === null) {
            return null;
        }

        if ($operator instanceof WrappingOperatorInterface) {
            // do magic to unwrap
            return $operator->wrap($fieldCondition, $context, $parent, fn(FieldConditionInterface $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): mixed => $this->processFieldConfig($fieldCondition, $context, $parent));
        }

        return $operator->process($fieldCondition, $context, $parent);
    }

    /**
     * null means no match / result back means match
     *
     * @param array $result
     * @param FieldConditionInterface $fieldCondition
     * @param ResultOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return array|null
     */
    private function processPostResultFieldConfig(array $result, FieldConditionInterface $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent): ?array
    {
        $operator = $this->findPostResultOperator($fieldCondition, $context, $parent);
        // no operator found, mark as matched
        if ($operator === null) {
            return $result;
        }

        if ($operator instanceof WrappingOperatorInterface) {
            // do magic to unwrap
            return $operator->postResultWrap($result, $fieldCondition, $context, $parent, fn(array $result, FieldConditionInterface $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent): ?array => $this->processPostResultFieldConfig($result, $fieldCondition, $context, $parent));
        }

        return $operator->postResultProcess($result, $fieldCondition, $context, $parent);
    }

    /**
     * @param FieldConditionInterface $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return QueryOperatorInterface|null
     */
    private function findQueryBuilderOperator(FieldConditionInterface $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): ?QueryOperatorInterface
    {
        $operator = $this->findOperator($this->queryOperators, $fieldCondition, $context, $parent);
        if ($operator instanceof QueryOperatorInterface) {
            return $operator;
        }

        return null;
    }

    /**
     * @param FieldConditionInterface $fieldCondition
     * @param ResultOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return ResultOperatorInterface|null
     */
    private function findPostResultOperator(FieldConditionInterface $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent): ?ResultOperatorInterface
    {
        $operator = $this->findOperator($this->resultOperators, $fieldCondition, $context, $parent);
        if ($operator instanceof ResultOperatorInterface) {
            return $operator;
        }

        return null;
    }

    /**
     * @param iterable<OperatorInterface> $operators
     * @param FieldConditionInterface $fieldCondition
     * @param OperatorContext $context
     * @param OperatorHistory|null $parent
     * @return OperatorInterface|null
     */
    private function findOperator(iterable $operators, FieldConditionInterface $fieldCondition, OperatorContext $context, ?OperatorHistory $parent): ?OperatorInterface
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
     * transform basic Conditions into FieldConditions
     *
     * @param string $fieldName
     * @param mixed $condition
     * @return FieldConditionInterface
     */
    private function generateFieldCondition(string $fieldName, mixed $condition): FieldConditionInterface
    {
        return match (true) {
            $condition instanceof FieldConditionInterface => $condition,
            default => new FieldCondition($fieldName, $condition),
        };
    }
}
