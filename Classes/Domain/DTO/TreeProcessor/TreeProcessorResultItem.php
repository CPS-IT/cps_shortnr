<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Domain\DTO\TreeProcessor;

class TreeProcessorResultItem implements TreeProcessorResultItemInterface
{
    /**
     * @var mixed
     */
    private mixed $data = null;
    /**
     * [objectId]
     * @var array<int, TreeProcessorResultItemInterface> - Using object IDs for O(1) lookups
     */
    private array $children = [];
    /**
     * [languageId][objectId]
     *
     * @var array<int, array<int, TreeProcessorResultItemInterface>> - Using object IDs for O(1) lookups
     */
    private array $languageReference = [];
    /**
     * @var null|TreeProcessorResultItemInterface
     */
    private ?TreeProcessorResultItemInterface $parent = null;
    private ?TreeProcessorResultItemInterface $languageBase = null;

    private ?int $primaryId = null;
    private ?int $languageId = null;
    private bool $isFresh = true;

    public function __serialize(): array
    {
        return [
            'pk' => $this->primaryId,
            'lk' => $this->languageId,
            'd' => $this->data,
            'c' => $this->children,
            'lr' => $this->languageReference,
            'p' => $this->parent,
            'lb' => $this->languageBase,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data['d'];
        $this->children = $data['c'];
        $this->languageReference = $data['lr'];
        $this->parent = $data['p'];
        $this->languageBase = $data['lb'];
        $this->primaryId = $data['pk'];
        $this->languageId = $data['lk'];
        $this->isFresh = false;
    }

    /**
     * flag if this item already has data initialized
     * unserialized objects are always NOT fresh
     *
     * @internal
     * @return bool
     */
    public function isFresh(): bool
    {
        return $this->isFresh;
    }

    /**
     * @param mixed $data
     */
    public function setData(mixed $data): void
    {
        $this->isFresh = false;
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @return int|null
     */
    public function getPrimaryId(): ?int
    {
        return $this->primaryId;
    }

    /**
     * @param int|null $primaryId
     * @return TreeProcessorResultItemInterface
     */
    public function setPrimaryId(?int $primaryId): TreeProcessorResultItemInterface
    {
        $this->primaryId = $primaryId;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLanguageId(): ?int
    {
        return $this->languageId;
    }

    /**
     * @param int|null $languageId
     * @return TreeProcessorResultItemInterface
     */
    public function setLanguageId(?int $languageId): TreeProcessorResultItemInterface
    {
        $this->languageId = $languageId;
        $this->addLanguageReference($this, $languageId);
        return $this;
    }

    /**
     * @param TreeProcessorResultItemInterface ...$children
     */
    public function addChild(TreeProcessorResultItemInterface ...$children): void
    {
        foreach ($children as $child) {
            $uid = spl_object_id($child);
            // O(1) duplicate check using object ID as key
            if (!isset($this->children[$uid])) {
                $this->children[$uid] = $child;
                $child->setParentInternal($this);
            }
        }
    }

    public function addLanguageReference(TreeProcessorResultItemInterface $reference, int $languageId): void
    {
        $uid = spl_object_id($reference);
        if (!isset($this->children[$languageId][$uid])) {
            $this->languageReference[$languageId][$uid] = $reference;
            if ($reference !== $this) {
                $reference->setLanguageBaseInternal($this);
            }
        }
    }

    /**
     * @return iterable<TreeProcessorResultItemInterface>
     */
    public function getChildren(): iterable
    {
        foreach ($this->children as $child) {
            yield $child;
        }
    }

    /**
     * Internal method to set parent without triggering addChild
     * @param TreeProcessorResultItemInterface|null $parent
     */
    private function setParentInternal(?TreeProcessorResultItemInterface $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * Internal method to set parent without triggering addChild
     * @param TreeProcessorResultItemInterface|null $base
     */
    private function setLanguageBaseInternal(?TreeProcessorResultItemInterface $base): void
    {
        $this->languageBase = $base;
    }

    /**
     * @return TreeProcessorResultItemInterface|null
     */
    public function getParent(): ?TreeProcessorResultItemInterface
    {
        return $this->parent;
    }

    /**
     * Simple O(depth) root traversal - optimal for small trees (500 items, 2-4 levels)
     * @return TreeProcessorResultItemInterface
     */
    public function getRoot(): TreeProcessorResultItemInterface
    {
        // we use iteration to avoid function jumps (recursion) that are slower and can cause stack overflow
        $current = $this;
        while ($current->parent !== null) {
            $current = $current->parent;
        }
        return $current;
    }

    /**
     * Get translation for specific language ID
     * @param int $languageId
     * @return TreeProcessorResultItemInterface|null
     */
    public function getTranslation(int $languageId): ?TreeProcessorResultItemInterface
    {
        return $this->getAllTranslations()[$languageId] ?? null;
    }

    /**
     * Get all available translations as [languageId => TreeProcessorResultItemInterface]
     * @return array<int, TreeProcessorResultItemInterface>
     */
    public function getAllTranslations(): array
    {
        return $this->languageBase?->getAllTranslations() ?? $this->flattenLanguageReferences();
    }

    /**
     * Check if translation exists for language ID
     * @param int $languageId
     * @return bool
     */
    public function hasTranslation(int $languageId): bool
    {
        // load from base if inside reference since the base store reference information
        return $this->languageBase?->hasTranslation($languageId) ?? !empty($this->languageReference[$languageId]);
    }

    /**
     * Get all available language IDs
     * @return int[]
     */
    public function getAvailableLanguageIds(): array
    {
        return $this->languageBase?->getAvailableLanguageIds() ?? array_keys($this->languageReference);
    }

    /**
     * Get the base translation item (language 0 equivalent)
     * Returns self if already base, otherwise returns the base item
     * @return TreeProcessorResultItemInterface
     */
    public function getBaseTranslation(): TreeProcessorResultItemInterface
    {
        return $this->languageBase ?? $this;
    }

    /**
     * this will only be executed in the BaseLanguage Item
     *
     * Flatten 2-level languageReference array to [languageId => TreeProcessorResultItemInterface]
     * Handles TYPO3 edge cases and ensures only first item per language is returned
     * @return array<int, TreeProcessorResultItemInterface>
     */
    private function flattenLanguageReferences(): array
    {
        return $this->languageBase?->flattenLanguageReferences() ?? array_map('reset', $this->languageReference);
    }
}
