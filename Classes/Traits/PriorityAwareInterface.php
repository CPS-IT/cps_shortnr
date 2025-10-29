<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Traits;

interface PriorityAwareInterface
{
    /**
     * if more than one operator can serve the same operation, the one with the highest priority will be used
     *
     * @return int (default: 0)
     */
    public function getPriority(): int;
}
