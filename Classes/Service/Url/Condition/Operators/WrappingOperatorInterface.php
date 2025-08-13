<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Config\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\EncodingOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\ResultOperatorContext;
use Doctrine\DBAL\Query\Expression\CompositeExpression;

interface WrappingOperatorInterface extends QueryOperatorInterface, ResultOperatorInterface
{
    /**
     * @param FieldConditionInterface $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @param callable $nestedCallback
     * @return array|string|CompositeExpression|null
     */
    public function wrap(FieldConditionInterface $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent, callable $nestedCallback): null|string|array|CompositeExpression;

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
    public function postResultWrap(array $result, FieldConditionInterface $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent, callable $nestedCallback): ?array;

    /**
     * encoding validation, only validate static variables, ignores any dynamic "placeholder" (match-N)
     *
     * @param array $data
     * @param FieldConditionInterface $fieldCondition
     * @param EncodingOperatorContext $context
     * @param OperatorHistory|null $parent
     * @param callable $nestedCallback
     * @return bool
     */
    public function encodingWrap(array $data, FieldConditionInterface $fieldCondition, EncodingOperatorContext $context, ?OperatorHistory $parent, callable $nestedCallback): bool;
}
