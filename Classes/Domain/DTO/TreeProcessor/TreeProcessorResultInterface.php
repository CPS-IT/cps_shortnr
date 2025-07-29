<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Domain\DTO\TreeProcessor;

interface TreeProcessorResultInterface
{
    /**
     * @return TreeProcessorResultItemInterface[]
     */
    public function getRootItems(): array;

    /**
     * Generator version for memory-efficient iteration over root items
     * @return \Generator<TreeProcessorResultItemInterface>
     */
    public function getRootItemsGenerator(): \Generator;

    /**
     * @param int $id
     * @return TreeProcessorResultItemInterface|null
     */
    public function getItem(int $id): ?TreeProcessorResultItemInterface;

    /**
     * @return int
     */
    public function count(): int;
}
