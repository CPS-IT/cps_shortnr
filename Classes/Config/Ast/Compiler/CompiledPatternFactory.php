<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Compiler;

use CPSIT\ShortNr\Config\Ast\Nodes\AstNode;
use CPSIT\ShortNr\Config\Ast\Nodes\GroupNode;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NodeTreeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\TypeNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\LiteralNode;
use CPSIT\ShortNr\Config\Ast\Nodes\SequenceNode;
use CPSIT\ShortNr\Config\Ast\Nodes\SubSequenceNode;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternCompilationException;
use CPSIT\ShortNr\Exception\ShortNrPatternException;
use CPSIT\ShortNr\Exception\ShortNrPatternParseException;
use CPSIT\ShortNr\Exception\ShortNrPatternTypeException;

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
     *
     * @param string $pattern
     * @param AstNode $ast
     * @return CompiledPattern
     * @throws ShortNrPatternParseException
     * @throws ShortNrPatternTypeException
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
     * @throws ShortNrPatternCompilationException
     * @throws ShortNrPatternException
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
    private function serializeAst(AstNodeInterface $node): array
    {
        return $node->toArray();
    }

    /**
     * Deserialize AST from array structure
     * @throws ShortNrPatternCompilationException
     * @throws ShortNrPatternCompilationException
     * @throws ShortNrPatternCompilationException
     * @throws ShortNrPatternException
     */
    private function deserializeAst(array $data): AstNodeInterface
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
     * @param AstNodeInterface $node
     * @param array $namedGroups
     * @param array $groupTypes
     * @param array $groupConstraints
     * @return void
     * @throws ShortNrPatternParseException
     * @throws ShortNrPatternTypeException
     */
    private function extractGroupInfo(
        AstNodeInterface $node,
        array &$namedGroups,
        array &$groupTypes,
        array &$groupConstraints
    ): void {
        if ($node instanceof TypeNodeInterface) {
            $groupId = $node->getGroupId();
            $namedGroups[$groupId] = $node->getName();
            $groupTypes[$node->getName()] = $node->getType()->getDefaultName();
            $groupConstraints[$node->getName()] = $node->getType()->getConstraintArguments();
        } elseif ($node instanceof NodeTreeInterface) {
            foreach ($node->getChildren() as $child) {
                $this->extractGroupInfo($child, $namedGroups, $groupTypes, $groupConstraints);
            }
        }
    }
}
