<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\QueryOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\ResultOperatorContext;
use Doctrine\DBAL\Query\Expression\CompositeExpression;

interface WrappingOperatorInterface extends QueryOperatorInterface, ResultOperatorInterface
{
    /**
     * @param FieldCondition $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @param callable $nestedCallback
     * @return array|string|CompositeExpression|null
     */
    public function wrap(FieldCondition $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent, callable $nestedCallback): null|string|array|CompositeExpression;

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
    public function postResultWrap(array $result, FieldCondition $fieldCondition, ResultOperatorContext $context, ?OperatorHistory $parent, callable $nestedCallback): ?array;
}
