<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;

interface OperatorInterface
{
    /**
     * check if that operator can handle the given fieldConfig
     *
     * @param FieldCondition $fieldCondition
     * @param OperatorContext $context
     * @param OperatorHistory|null $parent
     * @return bool return true if the operator can support that fieldConfig otherwise false
     */
    public function supports(FieldCondition $fieldCondition, OperatorContext $context, ?OperatorHistory $parent): bool;

    /**
     * if more than one operator can serve the same operation, the one with the highest priority will be used
     *
     * @return int (default: 0)
     */
    public function getPriority(): int;
}
