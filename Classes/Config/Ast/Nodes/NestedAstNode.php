<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternCompilationException;

abstract class NestedAstNode extends NamedAstNode
{
    /** @var AstNode[] */
    protected array $children = [];

    public function addChild(AstNode $node): void
    {
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
            $names = array_merge($names, $child->getGroupNames());
        }
        return $names;
    }

    /**
     * @return string
     */
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

    private static function createNodeFromArray(array $data, ?TypeRegistry $typeRegistry = null): AstNode
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
