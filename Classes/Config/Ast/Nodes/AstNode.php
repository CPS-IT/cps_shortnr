<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use RuntimeException;

abstract class AstNode
{
    private ?string $regex = null;
    private ?AstNode $parent = null;


    /**
     * Convert this node to its regex representation.
     */
    public function toRegex(): string
    {
        return $this->regex ??= $this->generateRegex();
    }

    /**
     * @param string|null $regex
     */
    protected function setRegex(?string $regex): void
    {
        $this->regex = $regex;
    }

    /**
     * Set the parent node with safety checks
     */
    public function setParent(?AstNode $parent): void
    {
        // Prevent circular references
        if ($parent === $this) {
            throw new RuntimeException("Cannot set self as parent");
        }
        $this->parent = $parent;
    }

    /**
     * Get the parent node
     */
    public function getParent(): ?AstNode
    {
        return $this->parent;
    }

    /**
     * Check if this node is optional by delegating to parent.
     * SubSequenceNode overrides this to return true.
     */
    public function isOptional(): bool
    {
        return $this->parent?->isOptional() ?? false;
    }

    /**
     * Validate tree context after all parent-child relationships are established.
     * This method should be called on the root node to validate the entire tree.
     */
    public function validateTreeContext(): void
    {
        // Default implementation does nothing - subclasses override as needed
    }

    /**
     * Recursively validate the entire tree starting from this node.
     */
    public function validateEntireTree(): void
    {
        $this->validateTreeContext();
        
        // If this is a nested node, validate all children
        if (method_exists($this, 'getChildren')) {
            foreach ($this->getChildren() as $child) {
                $child->validateEntireTree();
            }
        }
    }

    /**
     * regex generator
     * @return string
     */
    abstract protected function generateRegex(): string;

    /**
     * Generate a string representation using the provided values.
     *
     * @param array<string, mixed> $values Associative array of group names to values
     * @throws RuntimeException When required values are missing
     */
    abstract public function generate(array $values): string;

    /**
     * Get all group names defined in this node and its children.
     *
     * @return list<string> List of group names
     */
    abstract public function getGroupNames(): array;

    /**
     * Check if this node contains optional elements.
     * Default implementation for leaf nodes - they don't contain other elements.
     * Container nodes override this to check their children.
     */
    public function hasOptional(): bool
    {
        return false;
    }

    /**
     * Convert this node to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'regex' => $this->toRegex()
        ];
    }

    /**
     * Create node from array data.
     */
    abstract public static function fromArray(array $data, ?TypeRegistry $typeRegistry = null): static;
}
