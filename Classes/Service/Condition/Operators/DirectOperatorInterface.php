<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Condition\Operators;

use CPSIT\ShortNr\Service\Condition\Operators\DTO\DirectOperatorContext;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\OperatorHistory;

interface DirectOperatorInterface extends OperatorInterface
{
    /**
     * @param array $data
     * @param FieldConditionInterface $fieldCondition
     * @param DirectOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return array|null
     */
    public function directProcess(array $data, FieldConditionInterface $fieldCondition, DirectOperatorContext $context, ?OperatorHistory $parent): ?array;
}
