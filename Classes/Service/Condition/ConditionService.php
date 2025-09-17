<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Condition;

use CPSIT\ShortNr\Service\Condition\Operators\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\QueryOperatorContext;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\ResultOperatorContext;
use CPSIT\ShortNr\Service\Condition\Operators\OperatorInterface;
use CPSIT\ShortNr\Service\Condition\Operators\QueryOperatorInterface;
use CPSIT\ShortNr\Service\Condition\Operators\ResultOperatorInterface;
use CPSIT\ShortNr\Service\Condition\Operators\WrappingOperatorInterface;
use CPSIT\ShortNr\Traits\SortPriorityIterableTrait;

class ConditionService
{
    use SortPriorityIterableTrait;

    /**
     * @var array<QueryOperatorInterface>
     */
    private readonly array $queryOperators;
    /**
     * @var array<ResultOperatorInterface>
     */
    private readonly array $resultOperators;
    /**
     * @var array<ResultOperatorInterface>
     */
    private readonly array $encodingOperators;


    /**
     * @param iterable<QueryOperatorInterface> $queryOperators
     * @param iterable<ResultOperatorInterface> $resultOperators
     */
    public function __construct(
        iterable $queryOperators,
        iterable $resultOperators
    )
    {
        // sort By Priority
        $this->queryOperators = $this->sortIteratableByPrioity($queryOperators);
        $this->resultOperators = $this->sortIteratableByPrioity($resultOperators);
    }

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
        foreach ($operators as $operator) {
            if ($operator->supports($fieldCondition, $context, $parent)) {
                return $operator;
            }
        }

        return null;
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
