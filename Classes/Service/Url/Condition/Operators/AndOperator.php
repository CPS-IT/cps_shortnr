<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Config\DTO\FieldCondition;
use CPSIT\ShortNr\Config\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\EncodingOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\ResultOperatorContext;
use Doctrine\DBAL\Query\Expression\CompositeExpression;

class AndOperator implements WrappingOperatorInterface, EncodingOperatorInterface
{
    /**
     * @param FieldConditionInterface $fieldCondition
     * @param OperatorContext $context
     * @param OperatorHistory|null $parent
     * @return bool
     */
    public function supports(FieldConditionInterface $fieldCondition, OperatorContext $context, ?OperatorHistory $parent): bool
    {
        $condition = $fieldCondition->getCondition();
        return $context->fieldExists($fieldCondition->getFieldName()) && is_array($condition) && count($condition) > 1 && !array_is_list($condition);
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @param FieldConditionInterface $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return string|null
     */
    public function process(FieldConditionInterface $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): ?string
    {
        return null;
    }

    /**
     * @param array $result
     * @param FieldConditionInterface $fieldCondition
     * @param ResultOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return array|null
     */
    public function postResultProcess(array $result, FieldConditionInterface $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent): ?array
    {
        return null;
    }

    /**
     * @param array $data
     * @param FieldConditionInterface $fieldCondition
     * @param EncodingOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return bool
     */
    public function encodingProcess(array $data, FieldConditionInterface $fieldCondition, EncodingOperatorContext $context, ?OperatorHistory $parent): bool
    {
        return false;
    }

    /**
     * adds an and condition to the DB query
     *
     * @param FieldConditionInterface $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @param callable $nestedCallback
     * @return null|CompositeExpression
     */
    public function wrap(FieldConditionInterface $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent, callable $nestedCallback): ?CompositeExpression
    {
        $condition = $fieldCondition->getCondition();
        // early exit with no deep branching here since its empty
        if (empty($condition)) {
            return null;
        }

        $andConditions = [];
        $operatorHistory = new OperatorHistory($parent, $this);
        foreach ($condition as $operatorName => $fieldConfigSegment) {
            $andConditions[] = $nestedCallback(
                new FieldCondition(
                    $fieldCondition->getFieldName(),
                    [$operatorName => $fieldConfigSegment]
                ),
                $context,
                $operatorHistory
            );
        }

        $conditionsLeft = array_filter($andConditions);
        if (empty($conditionsLeft)) {
            return null;
        }

        return $context->getQueryBuilder()->expr()->and(
            ...$conditionsLeft
        );
    }

    /**
     * filter Query Results based on the config
     *
     * @param array $result
     * @param FieldConditionInterface $fieldCondition
     * @param ResultOperatorContext $context
     * @param OperatorHistory|null $parent
     * @param callable $nestedCallback
     * @return array|null
     */
    public function postResultWrap(array $result, FieldConditionInterface $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent, callable $nestedCallback): ?array
    {
        $condition = $fieldCondition->getCondition();
        // Early return for empty config
        if (empty($condition)) {
            return $result;
        }

        // Create operator history once to reuse
        $operatorHistory = new OperatorHistory($parent, $this);
        foreach ($condition as $operatorName => $fieldConfigSegment) {
            $conditionResult = $nestedCallback(
                new FieldCondition(
                    $fieldCondition->getFieldName(),
                    [$operatorName => $fieldConfigSegment]
                ),
                $result,
                $operatorHistory
            );

            // In AND operation, if any condition is null/false, entire result is null
            if ($conditionResult === null) {
                return null;
            }
        }

        // All conditions passed
        return $result;
    }

    /**
     * @param array $data
     * @param FieldConditionInterface $fieldCondition
     * @param EncodingOperatorContext $context
     * @param operatorhistory|null $parent
     * @param callable $nestedCallback
     * @return bool
     */
    public function encodingWrap(array $data, FieldConditionInterface $fieldCondition, EncodingOperatorContext $context, ?OperatorHistory $parent, callable $nestedCallback): bool
    {
        $condition = $fieldCondition->getCondition();
        // Early return for empty config
        if (empty($condition)) {
            return true;
        }

        $operatorHistory = new OperatorHistory($parent, $this);
        foreach ($condition as $operatorName => $fieldConfigSegment) {
            // if any subCondition fails, ALL FAIL
            if(!$nestedCallback(
                $data,
                new FieldCondition(
                    $fieldCondition->getFieldName(),
                    [$operatorName => $fieldConfigSegment]
                ),
                $context,
                $operatorHistory
            )) {
                return false;
            }
        }
        // all sub conditions match So AND is Satisfied
        return true;
    }
}
