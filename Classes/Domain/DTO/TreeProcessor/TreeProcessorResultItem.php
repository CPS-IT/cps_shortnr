<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Domain\DTO\TreeProcessor;

class TreeProcessorResultItem implements TreeProcessorResultItemInterface
{
    /**
     * @var mixed
     */
    private mixed $data = null;
    /**
     * @var array<int, TreeProcessorResultItemInterface> - Using object IDs for O(1) lookups
     */
    private array $children = [];
    /**
     * @var null|TreeProcessorResultItemInterface
     */
    private ?TreeProcessorResultItemInterface $parent = null;

    public function __serialize(): array
    {
        return [
            'd' => $this->data,
            'c' => $this->children,
            'p' => $this->parent,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data['d'];
        $this->children = $data['c'];
        $this->parent = $data['p'];
    }

    /**
     * @param mixed $data
     */
    public function setData(mixed $data): void
    {
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
     * @param TreeProcessorResultItemInterface|null $parent
     */
    public function setParent(?TreeProcessorResultItemInterface $parent): void
    {
        // Prevent cycles by checking if parent is already in ancestry
        if ($parent !== null && $this->wouldCreateCycle($parent)) {
            return;
        }

        $this->parent = $parent;
        $parent?->addChild($this);
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
     * Check if setting this parent would create a cycle
     * @param TreeProcessorResultItemInterface $potentialParent
     * @return bool
     */
    private function wouldCreateCycle(TreeProcessorResultItemInterface $potentialParent): bool
    {
        $current = $potentialParent;
        while ($current !== null) {
            if ($current === $this) {
                return true;
            }
            $current = $current->getParent();
        }
        return false;
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
        $current = $this;
        while ($current->parent !== null) {
            $current = $current->parent;
        }
        return $current;
    }
}
