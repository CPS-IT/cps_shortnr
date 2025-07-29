<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\ResultOperatorContext;
use Doctrine\DBAL\Query\Expression\CompositeExpression;

class NotOperator implements WrappingOperatorInterface
{
    /**
     * @param FieldCondition $fieldCondition
     * @param OperatorContext $context
     * @param OperatorHistory|null $parent
     * @return bool
     */
    public function supports(FieldCondition $fieldCondition, OperatorContext $context, ?OperatorHistory $parent): bool
    {
        $fieldConfig = $fieldCondition->getCondition();
        return $context->fieldExists($fieldCondition->getFieldName()) && is_array($fieldConfig) && array_key_exists('not', $fieldConfig);
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @param FieldCondition $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return mixed
     */
    public function process(FieldCondition $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): mixed
    {
        return null;
    }

    /**
     * @param array $result
     * @param FieldCondition $fieldCondition
     * @param ResultOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return array|null
     */
    public function postResultProcess(array $result, FieldCondition $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent): ?array
    {
        return null;
    }

    /**
     * @param FieldCondition $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @param callable $nestedCallback
     * @return array|string|CompositeExpression|null
     */
    public function wrap(FieldCondition $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent, callable $nestedCallback): null|string|array|CompositeExpression
    {
        $condition = $fieldCondition->getCondition();
        if (!array_key_exists('not', $condition)) {
            return null;
        }

        return $nestedCallback(
            new FieldCondition(
                $fieldCondition->getFieldName(),
                $condition['not']
            ),
            $context,
            new OperatorHistory($parent, $this)
        );
    }

    /**
     * filter Query Results based on the config
     *
     * @param array $result
     * @param FieldCondition $fieldCondition
     * @param ResultOperatorContext $context
     * @param OperatorHistory|null $parent
     * @param callable $nestedCallback
     * @return array|null
     */
    public function postResultWrap(array $result, FieldCondition $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent, callable $nestedCallback): ?array
    {
        $condition = $fieldCondition->getCondition();
        if (!array_key_exists('not', $condition)) {
            return null;
        }

        return $nestedCallback(
            $result,
            new FieldCondition(
                $fieldCondition->getFieldName(),
                $condition['not']
            ),
            $context,
            new OperatorHistory($parent, $this)
        );
    }
}
