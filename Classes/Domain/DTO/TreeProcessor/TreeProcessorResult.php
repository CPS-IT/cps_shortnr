<?php

namespace CPSIT\ShortNr\Domain\DTO\TreeProcessor;

use Generator;

class TreeProcessorResult implements TreeProcessorGeneratorInterface
{
    /**
     * @var TreeProcessorResultItemInterface[]
     */
    private array $list = [];

    /**
     * @var TreeProcessorResultItemInterface[] - O(1) root item tracking
     */
    private array $rootItems = [];

    public function __serialize(): array
    {
        return [
            'r' => $this->rootItems,
            'l' => $this->list,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->rootItems = $data['r'];
        $this->list = $data['l'];
    }

    /**
     * @return TreeProcessorResultItemInterface[]
     */
    public function getRootItems(): array
    {
        return array_values($this->rootItems);
    }

    /**
     * Generator version for memory-efficient iteration over root items
     * @return Generator<TreeProcessorResultItemInterface>
     */
    public function getRootItemsGenerator(): Generator
    {
        yield from $this->rootItems;
    }

    protected function getNewItemObject(): TreeProcessorResultItemInterface
    {
        return new TreeProcessorResultItem();
    }

    /**
     * @param int $id
     * @param bool $createIfNotExists
     * @return TreeProcessorResultItemInterface|null
     */
    public function getItem(int $id, bool $createIfNotExists = false): ?TreeProcessorResultItemInterface
    {
        if ($createIfNotExists) {
            return $this->list[$id] ??= $this->getNewItemObject();
        }

        return $this->list[$id] ?? null;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->list);
    }

    /**
     * @param TreeProcessorDataInterface $data
     * @param mixed $item
     */
    public function processData(TreeProcessorDataInterface $data, mixed $item): void
    {
        $id = $data->getPrimaryIdFromData($item);
        if ($id <= 0) {
            return;
        }

        $itemObj = $this->setItemData($id, $data, $item);
        $rid = $data->getRelationIdFromData($item);

        if ($rid > 0) {
            $parentItem = $this->getItem($rid, true);
            $parentItem->addChild($itemObj);
            unset($this->rootItems[$id]);
        } else {
            $this->rootItems[$id] = $itemObj;
        }
    }


    /**
     * @param int $id
     * @param TreeProcessorDataInterface $data
     * @param mixed $item
     * @return TreeProcessorResultItemInterface
     */
    protected function setItemData(int $id, TreeProcessorDataInterface $data, mixed $item): TreeProcessorResultItemInterface
    {
        $itemObj = $this->list[$id] ??= $this->getNewItemObject();
        $itemObj->setData($item);

        return $itemObj;
    }
}
