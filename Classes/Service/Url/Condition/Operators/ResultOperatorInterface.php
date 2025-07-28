<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;

interface ResultOperatorInterface extends OperatorInterface
{
    /**
     * @param string $fieldName
     * @param mixed $fieldConfig
     * @param array $result
     * @param OperatorHistoryInterface|null $parent
     * @return array|null
     */
    public function postResultProcess(string $fieldName, mixed $fieldConfig, array $result, ?OperatorHistoryInterface $parent): ?array;

}
