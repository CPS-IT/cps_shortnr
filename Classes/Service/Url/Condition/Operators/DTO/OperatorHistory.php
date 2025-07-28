<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition\Operators\DTO;

use CPSIT\ShortNr\Service\Url\Condition\Operators\OperatorInterface;

class OperatorHistory implements OperatorHistoryInterface
{
    /**
     * @param OperatorHistoryInterface|null $parent
     * @param OperatorInterface $operator
     */
    public function __construct(
        private readonly ?OperatorHistoryInterface $parent,
        private readonly OperatorInterface $operator
    )
    {}

    /**
     * @param string $className
     * @return bool
     */
    public function hasOperatorTypeInHistory(string $className): bool
    {
        if ($className === get_class($this->operator)) {
            return true;
        } elseif ($this->parent) {
            return $this->parent->hasOperatorTypeInHistory($className);
        }

        return false;
    }
}
