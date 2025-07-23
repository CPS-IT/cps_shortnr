<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators\DTO;

interface OperatorHistoryInterface
{
    public function hasOperatorTypeInHistory(string $className): bool;
}
