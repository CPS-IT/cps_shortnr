<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Condition\Operators;

use CPSIT\ShortNr\Service\Condition\Operators\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\QueryOperatorContext;

interface QueryOperatorInterface extends OperatorInterface
{
    /**
     * @param FieldConditionInterface $fieldCondition
     * @param QueryOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return mixed
     */
    public function process(FieldConditionInterface $fieldCondition, QueryOperatorContext $context, ?OperatorHistory $parent): mixed;
}
