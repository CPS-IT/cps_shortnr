<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\BoundaryProviderInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NestedNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NodeGroupAwareInterface;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternCompilationException;

abstract class NestedAstNode extends NamedAstNode implements NestedNodeInterface
{
    /** @var AstNodeInterface[] */
    protected array $children = [];

    public function getBoundary(): ?string
    {
        return null; // Nested nodes don't directly provide boundaries
    }

    public function getFirstBoundary(): ?string
    {
        foreach ($this->children as $child) {
            if ($child instanceof BoundaryProviderInterface) {
                $boundary = $child->getFirstBoundary();
                if ($boundary !== null) {
                    return $boundary;
                }
            }
        }
        return null;
    }

    public function addChild(AstNodeInterface $node): void
    {
        if ($node->getParent() === null) {
            $node->setParent($this);
        }
        $this->children[] = $node;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getGroupNames(): array
    {
        $names = [];
        foreach ($this->children as $child) {
            if ($child instanceof NodeGroupAwareInterface)
                $names = array_merge($names, $child->getGroupNames());
        }
        return $names;
    }

    protected function generateRegex(): string
    {
        $regex = '';
        foreach ($this->children as $child) {
            $regex .= $child->toRegex();
        }
        return $regex;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => $this->getNodeType(),
            'children' => array_map(
                fn($child) => $child->toArray(),
                $this->children
            )
        ];
    }

    public static function fromArray(array $data, ?TypeRegistry $typeRegistry = null): static
    {
        $node = new static();
        foreach ($data['children'] as $childData) {
            $childNode = self::createNodeFromArray($childData, $typeRegistry);
            $childNode->setRegex($childData['regex'] ?? null);
            $node->addChild($childNode);
        }
        $node->setRegex($data['regex'] ?? null);
        return $node;
    }

    private static function createNodeFromArray(array $data, ?TypeRegistry $typeRegistry = null): AstNodeInterface
    {
        return match ($data['type']) {
            'literal' => LiteralNode::fromArray($data),
            'group' => GroupNode::fromArray($data, $typeRegistry),
            'sequence' => SequenceNode::fromArray($data, $typeRegistry),
            'subsequence' => SubSequenceNode::fromArray($data, $typeRegistry),
            default => throw new ShortNrPatternCompilationException(
                "Unknown node type during deserialization: " . $data['type'],
                'nested_pattern',
                'unknown_regex'
            )
        };
    }
}
