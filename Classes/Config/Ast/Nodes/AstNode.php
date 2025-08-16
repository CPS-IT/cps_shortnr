<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NodeTreeInterface;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use RuntimeException;

abstract class AstNode implements AstNodeInterface
{
    private ?string $regex = null;
    private ?NodeTreeInterface $parent = null;

    public function toRegex(): string
    {
        return $this->regex ??= $this->generateRegex();
    }

    protected function setRegex(?string $regex): void
    {
        $this->regex = $regex;
    }

    public function setParent(?NodeTreeInterface $parent): void
    {
        if ($parent === $this) {
            throw new RuntimeException("Cannot set self as parent");
        }
        $this->parent = $parent;
    }

    public function getParent(): ?NodeTreeInterface
    {
        return $this->parent;
    }

    public function isOptional(): bool
    {
        return $this->parent?->isOptional() ?? false;
    }

    public function validateTreeContext(): void
    {
        // Default implementation - subclasses override as needed
    }

    public function validateEntireTree(): void
    {
        $this->validateTreeContext();
        
        if (method_exists($this, 'getChildren')) {
            foreach ($this->getChildren() as $child) {
                $child->validateEntireTree();
            }
        }
    }

    public function hasOptional(): bool
    {
        return false;
    }

    public function toArray(): array
    {
        return [
            'regex' => $this->toRegex()
        ];
    }

    abstract protected function generateRegex(): string;
    abstract public function generate(array $values): string;
    abstract public function getGroupNames(): array;
    abstract public static function fromArray(array $data, ?TypeRegistry $typeRegistry = null): static;
}
