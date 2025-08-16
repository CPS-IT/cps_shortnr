<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Compiler;

use CPSIT\ShortNr\Config\Ast\Nodes\AstNode;
use CPSIT\ShortNr\Config\Ast\Nodes\GroupNode;
use CPSIT\ShortNr\Config\Ast\Nodes\LiteralNode;
use CPSIT\ShortNr\Config\Ast\Nodes\NestedAstNode;
use CPSIT\ShortNr\Config\Ast\Nodes\SequenceNode;
use CPSIT\ShortNr\Config\Ast\Nodes\SubSequenceNode;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternCompilationException;

/**
 * Factory for creating and hydrating CompiledPattern instances
 */
final class CompiledPatternFactory
{
    public function __construct(
        private readonly TypeRegistry $typeRegistry
    ) {}

    /**
     * Create a new CompiledPattern from an AST
     */
    public function create(string $pattern, AstNode $ast): CompiledPattern
    {
        $namedGroups = [];
        $groupTypes = [];
        $groupConstraints = [];

        $this->extractGroupInfo($ast, $namedGroups, $groupTypes, $groupConstraints);

        $regex = '/^' . $ast->toRegex() . '$/';

        return new CompiledPattern(
            $pattern,
            $regex,
            $ast,
            $namedGroups,
            $groupTypes,
            $groupConstraints,
            $this->typeRegistry
        );
    }

    /**
     * Convert CompiledPattern to cacheable array
     */
    public function dehydrate(CompiledPattern $pattern): array
    {
        return [
            'version' => '1.0',
            'pattern' => $pattern->getPattern(),
            'regex' => $pattern->getRegex(),
            'ast' => $this->serializeAst($pattern->getAst()),
            'namedGroups' => $pattern->getNamedGroups(),
            'groupTypes' => $pattern->getGroupTypes(),
            'groupConstraints' => $pattern->getGroupConstraints(),
        ];
    }

    /**
     * Recreate CompiledPattern from cached data
     * Uses the current TypeRegistry instance
     */
    public function hydrate(array $data): CompiledPattern
    {
        $ast = $this->deserializeAst($data['ast']);

        return new CompiledPattern(
            $data['pattern'],
            $data['regex'],
            $ast,
            $data['namedGroups'],
            $data['groupTypes'],
            $data['groupConstraints'],
            $this->typeRegistry
        );
    }

    /**
     * Serialize AST to array structure
     */
    private function serializeAst(AstNode $node): array
    {
        return $node->toArray();
    }

    /**
     * Deserialize AST from array structure
     */
    private function deserializeAst(array $data): AstNode
    {
        return match ($data['type']) {
            'literal' => LiteralNode::fromArray($data, $this->typeRegistry),
            'group' => GroupNode::fromArray($data, $this->typeRegistry),
            'sequence' => SequenceNode::fromArray($data, $this->typeRegistry),
            'subsequence' => SubSequenceNode::fromArray($data, $this->typeRegistry),
            default => throw new ShortNrPatternCompilationException(
                "Unknown node type during deserialization: " . $data['type'],
                'cached_pattern',
                'unknown_regex'
            )
        };
    }


    /**
     * Extract group information from AST
     */
    private function extractGroupInfo(
        AstNode $node,
        array &$namedGroups,
        array &$groupTypes,
        array &$groupConstraints
    ): void {
        if ($node instanceof GroupNode) {
            $groupId = $node->getGroupId();
            $namedGroups[$groupId] = $node->getName();
            $groupTypes[$node->getName()] = $node->getType();
            $groupConstraints[$node->getName()] = $node->getConstraints();
        } elseif ($node instanceof NestedAstNode) {
            foreach ($node->getChildren() as $child) {
                $this->extractGroupInfo($child, $namedGroups, $groupTypes, $groupConstraints);
            }
        }
    }

    private function dumpAstStructure(AstNode $node, int $depth): void {
        $indent = str_repeat('  ', $depth);
        if ($node instanceof GroupNode) {
            echo "{$indent}GroupNode: {$node->getName()} ({$node->getType()}) [greedy=" . ($node->isGreedy() ? 'true' : 'false') . "]\n";
        } elseif ($node instanceof LiteralNode) {
            echo "{$indent}LiteralNode: '{$node->getText()}'\n";
        } elseif ($node instanceof SubSequenceNode) {
            echo "{$indent}SubSequenceNode:\n";
            foreach ($node->getChildren() as $child) {
                $this->dumpAstStructure($child, $depth + 1);
            }
        } elseif ($node instanceof SequenceNode) {
            echo "{$indent}SequenceNode:\n";
            foreach ($node->getChildren() as $child) {
                $this->dumpAstStructure($child, $depth + 1);
            }
        } else {
            echo "{$indent}" . get_class($node) . "\n";
        }
    }
}
