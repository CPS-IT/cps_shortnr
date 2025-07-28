<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

interface OperatorInterface
{
    /**
     * first comes, first serves logic
     *
     * check if that operator can handle the given fieldConfig
     *
     * @param mixed $fieldConfig
     * @return bool return true if the operator can support that fieldConfig otherwise false
     */
    public function supports(mixed $fieldConfig): bool;
}
