<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Config\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\EncodingOperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;

interface EncodingOperatorInterface extends OperatorInterface
{
    /**
     * encoding validation, only validate static variables, ignores any dynamic "placeholder" (match-N)
     *
     * @param array $data
     * @param FieldConditionInterface $fieldCondition
     * @param EncodingOperatorContext $context
     * @param OperatorHistory|null $parent
     * @return bool
     */
    public function encodingProcess(array $data, FieldConditionInterface $fieldCondition, EncodingOperatorContext $context, ?OperatorHistory $parent): bool;
}
