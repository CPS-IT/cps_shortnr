<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorContext;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\ResultOperatorContext;

class RegexMatchOperator implements ResultOperatorInterface
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
        return $context->fieldExists($fieldCondition->getFieldName()) && is_array($fieldConfig) && array_key_exists(ConfigEnum::ConditionRegexMatch->value, $fieldConfig);
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 0;
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
        // WIP! skip test for now!
        // TODO: add regex check ConfigEnum::ConditionRegexMatch->value
        return null;
    }
}
