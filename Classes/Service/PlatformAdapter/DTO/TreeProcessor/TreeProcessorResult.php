<?php

namespace CPSIT\ShortNr\Service\PlatformAdapter\DTO\TreeProcessor;

use Generator;

class TreeProcessorResult implements TreeProcessorGeneratorInterface
{
    private string|int|null $primaryKey = null;
    private string|int|null $relationKey = null;
    private string|int|null $languageKey = null;
    private string|int|null $languageRelationKey = null;
    /**
     * @var array<int, TreeProcessorResultItemInterface>
     */
    private array $list = [];

    /**
     * @var array<int, TreeProcessorResultItemInterface> - O(1) root item tracking
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
     * @internal
     * @param int|string|null $primaryKey
     * @return TreeProcessorGeneratorInterface
     */
    public function setPrimaryKey(int|string|null $primaryKey): TreeProcessorGeneratorInterface
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    /**
     * @internal
     * @param int|string|null $relationKey
     * @return TreeProcessorGeneratorInterface
     */
    public function setRelationKey(int|string|null $relationKey): TreeProcessorGeneratorInterface
    {
        $this->relationKey = $relationKey;
        return $this;
    }

    /**
     * @internal
     * @param int|string|null $languageKey
     * @return TreeProcessorGeneratorInterface
     */
    public function setLanguageKey(int|string|null $languageKey): TreeProcessorGeneratorInterface
    {
        $this->languageKey = $languageKey;
        return $this;
    }

    /**
     * @internal
     * @param int|string|null $languageRelationKey
     * @return TreeProcessorGeneratorInterface
     */
    public function setLanguageRelationKey(int|string|null $languageRelationKey): TreeProcessorGeneratorInterface
    {
        $this->languageRelationKey = $languageRelationKey;
        return $this;
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

    /**
     * @param int $primaryId
     * @return TreeProcessorResultItemInterface
     * @internal
     */
    protected function getNewItemObject(int $primaryId): TreeProcessorResultItemInterface
    {
        return new TreeProcessorResultItem(primaryId: $primaryId);
    }

    /**
     * @param int $id
     * @param bool $createIfNotExists
     * @return TreeProcessorResultItemInterface|null
     */
    public function getItem(int $id, bool $createIfNotExists = false): ?TreeProcessorResultItemInterface
    {
        if ($createIfNotExists) {
            return $this->list[$id] ??= $this->getNewItemObject($id);
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
     * @internal
     * @param TreeProcessorDataInterface $data
     * @param mixed $item
     */
    public function processData(TreeProcessorDataInterface $data, mixed $item): void
    {
        $id = $data->getPrimaryIdFromData($item);
        if ($id <= 0) {
            return;
        }

        $langId = $data->getLanguageIdFromData($item);
        $langRefId = $data->getLanguageRelationIdFromData($item);

        $itemObj = $this->setItemData($id, $langId, $data, $item);
        $rid = $data->getRelationIdFromData($item);

        if ($rid > 0) {
            $parentItem = $this->getItem($rid, true);
            $parentItem->addChild($itemObj);
            unset($this->rootItems[$id]);
        } else {
            $this->rootItems[$id] = $itemObj;
        }

        // define what is a baseItem what is a LanguageRefItem
        if ($langId > 0 && $langRefId > 0) {
            $defaultLanguageItem = $this->getItem($langRefId, true);
            $defaultLanguageItem->addLanguageReference($itemObj, $langId);
        }
    }

    /**
     * detects Overlay system = false, or Multi Tree Single language systems = true (default: false)
     *
     * @return bool
     */
    public function isMultiTreeLanguageSetup(): bool
    {
        if ($this->languageKey === null || $this->languageRelationKey === null) {
            return false;
        }

        foreach ($this->rootItems as $item) {
            $data = $item->getData();
            $langId = $data[$this->languageKey] ?? null;
            $langRefId = $data[$this->languageRelationKey] ?? null;
            if ($langId > 0 && $langRefId === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $id
     * @param int $languageId
     * @param TreeProcessorDataInterface $data
     * @param mixed $item
     * @return TreeProcessorResultItemInterface
     * @internal
     */
    protected function setItemData(int $id, int $languageId, TreeProcessorDataInterface $data, mixed $item): TreeProcessorResultItemInterface
    {
        $itemObj = $this->getItem($id, true);
        if ($itemObj->isShadow()) {
            $itemObj->setLanguageId($languageId);
            $itemObj->setData($item);
        }

        return $itemObj;
    }

    /**
     * Removes all branches that have no data, and remove all related children too.
     * This method identifies dead branches (nodes with shadow === true) and prunes them.
     *
     * @return void
     */
    public function removeDeadBranches(): void
    {
        foreach (array_filter($this->list, fn($i) => $i->isShadow()) as $zombie) {
            $this->annihilateNode($zombie);
        }

        // remove rootItems that are not in the "list" anymore
        $this->rootItems = array_intersect_key(
            $this->rootItems,
            $this->list
        );
    }

    /**
     * Recursively destroys a node and all its children.
     *
     * @param TreeProcessorResultItemInterface $item
     * @return void
     */
    private function annihilateNode(TreeProcessorResultItemInterface $item): void
    {
        foreach ($item->getChildren() as $child) {
            $this->annihilateNode($child);
        }

        unset($this->list[$item->getPrimaryId()]);
    }
}
