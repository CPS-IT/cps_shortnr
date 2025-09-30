<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Traits;

trait SortPriorityIterableTrait
{
    /**
     * @param iterable<PriorityAwareInterface> $list
     * @return array
     */
    private function sortIterableByPriority(iterable $list): array
    {
        $sortedNormalizers = [];
        foreach ($list as $item) {
            if ($item instanceof PriorityAwareInterface) {
                $sortedNormalizers[$item->getPriority()][] = $item;
            } else {
                $sortedNormalizers[PHP_INT_MIN][] = $item;
            }
        }
        krsort($sortedNormalizers, SORT_NUMERIC);
        $finalList = [];
        foreach ($sortedNormalizers as $subList) {
            array_push($finalList, ...$subList);
        }

        return $finalList;
    }
}
