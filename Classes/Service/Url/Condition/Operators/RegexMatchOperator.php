<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;

class RegexMatchOperator implements ResultOperatorInterface
{
    /**
     * @param mixed $fieldConfig
     * @return bool
     */
    public function supports(mixed $fieldConfig): bool
    {
        return is_array($fieldConfig) && array_key_exists('match', $fieldConfig);
    }

    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param array $result
     * @param OperatorHistoryInterface|null $parent
     * @return array|null
     */
    public function postResultProcess(string $fieldName, mixed $fieldConfig, array $result, ?OperatorHistoryInterface $parent): ?array
    {
        // WIP! skip test for now!
        // TODO: add regex check
        return null;
    }
}
