<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer;

use CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer\Type\TypeAnalyzer;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\LiteralNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NodeTreeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\TypeNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\SubSequenceNode;
use CPSIT\ShortNr\Exception\ShortNrPatternException;
use CPSIT\ShortNr\Exception\ShortNrPatternParseException;

class NodeAnalyzer
{
    /**
     * Analyze a node and extract heuristic properties
     *
     * @param AstNodeInterface $node
     * @return AnalyzerResult
     * @throws ShortNrPatternException
     */
    public static function analyzeNode(AstNodeInterface $node): AnalyzerResult
    {
        return match (true) {
            $node instanceof LiteralNodeInterface => self::analyzeLiteralNode($node),
            $node instanceof TypeNodeInterface => self::analyzeTypeNode($node),
            $node instanceof NodeTreeInterface => self::analyzeTreeNode($node),
            default => throw new ShortNrPatternException('Unknown node in Heuristic parser detected: ' . $node::class)
        };
    }

    /**
     * Analyze a type node (Group)
     *
     * @param TypeNodeInterface $typeNode
     * @return AnalyzerResult
     * @throws ShortNrPatternParseException
     */
    private static function analyzeTypeNode(TypeNodeInterface $typeNode): AnalyzerResult
    {
        // Get the type and analyze it using blackbox probing
        $type = $typeNode->getType();
        $typeAnalysis = TypeAnalyzer::analyzeType($type);

        // Adjust min/max based on optionality
        $minLen = $typeNode->isOptional() ? 0 : $typeAnalysis->getMinLen();
        $maxLen = $typeAnalysis->getMaxLen();

        // Types don't have literals
        $literals = [];

        return new AnalyzerResult(
            minLen: $minLen,
            maxLen: $maxLen,
            literals: $literals,
            allowedChars: $typeAnalysis->getAllowedChars(),
            prefix: null,
            suffix: null
        );
    }

    /**
     * Analyze a tree node (Sequence or SubSequence)
     *
     * @param NodeTreeInterface $node
     * @return AnalyzerResult
     * @throws ShortNrPatternException
     */
    private static function analyzeTreeNode(NodeTreeInterface $node): AnalyzerResult
    {
        $isOptional = $node instanceof SubSequenceNode;

        $minLen = 0;
        $maxLen = 0;
        $allowedChars = [];
        $literals = [];
        $prefix = null;
        $suffix = null;

        $children = $node->getChildren();
        if (empty($children)) {
            // Empty sequence - shouldn't happen but handle gracefully
            return new AnalyzerResult(
                minLen: 0,
                maxLen: 0,
                literals: [],
                allowedChars: [],
                prefix: null,
                suffix: null
            );
        }

        $childAnalyses = [];
        foreach ($children as $child) {
            $childAnalyses[] = self::analyzeNode($child);
        }

        // Calculate cumulative properties
        foreach ($childAnalyses as $i => $childAnalysis) {
            // Accumulate lengths
            $minLen += $childAnalysis->getMinLen();

            // Handle max length (null means unlimited)
            if ($childAnalysis->getMaxLen() === null) {
                $maxLen = null;
            } elseif ($maxLen !== null) {
                $maxLen += $childAnalysis->getMaxLen();
            }

            // Merge allowed chars (union)
            $allowedChars = $allowedChars + $childAnalysis->getAllowedChars();

            // Merge literals with proper required/optional handling
            foreach ($childAnalysis->getLiterals() as $literalText => $isRequired) {
                if (!isset($literals[$literalText])) {
                    // If this tree node is optional, all its literals become optional
                    $literals[$literalText] = $isOptional ? false : $isRequired;
                } elseif ($isRequired && !$isOptional) {
                    // Upgrade to required if not already and tree isn't optional
                    $literals[$literalText] = true;
                }
            }

            // Set prefix from first child
            if ($i === 0 && $childAnalysis->getPrefix() !== null) {
                $prefix = $childAnalysis->getPrefix();
            }

            // Set suffix from last child
            if ($i === count($childAnalyses) - 1 && $childAnalysis->getSuffix() !== null) {
                $suffix = $childAnalysis->getSuffix();
            }
        }

        // If entire tree is optional, min length is 0
        if ($isOptional) {
            $minLen = 0;
        }

        return new AnalyzerResult(
            minLen: $minLen,
            maxLen: $maxLen,
            literals: $literals,
            allowedChars: $allowedChars,
            prefix: $prefix,
            suffix: $suffix
        );
    }

    /**
     * Analyze a literal node
     *
     * @param LiteralNodeInterface $node
     * @return AnalyzerResult
     */
    private static function analyzeLiteralNode(LiteralNodeInterface $node): AnalyzerResult
    {
        $text = $node->getText();
        $literalTextLength = strlen($text);

        // Build allowed chars from literal text
        $allowedChars = [];
        for ($i = 0; $i < $literalTextLength; $i++) {
            $allowedChars[ord($text[$i])] = true;
        }

        return new AnalyzerResult(
            minLen: $node->isOptional() ? 0 : $literalTextLength,
            maxLen: $literalTextLength, // Literals have fixed length
            literals: [$text => !$node->isOptional()], // Required if not optional
            allowedChars: $allowedChars,
            prefix: $text,
            suffix: $text
        );
    }
}
