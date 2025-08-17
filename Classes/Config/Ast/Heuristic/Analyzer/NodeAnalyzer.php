<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer;

use CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer\Type\TypeAnalyzer;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\LiteralNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NodeTreeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\TypeNodeInterface;
use CPSIT\ShortNr\Exception\ShortNrPatternException;
use CPSIT\ShortNr\Exception\ShortNrPatternParseException;

class NodeAnalyzer
{
    /**
     * @param AstNodeInterface $node
     * @return AnalyzerResult
     * @throws ShortNrPatternException
     */
    public static function analyzeNode(AstNodeInterface $node): AnalyzerResult
    {
        return match (true) {
            $node instanceof LiteralNodeInterface => self::AnalyzeLiteralNode($node),
            $node instanceof TypeNodeInterface => self::AnalyzeTypeNode($node),
            $node instanceof NodeTreeInterface => self::AnalyzeTreeNode($node),
            default => throw new ShortNrPatternException('unknown node in Heuristic parser detected: ' . $node::class)
        };
    }

    /**
     * @param TypeNodeInterface $typeNode
     * @return AnalyzerResult
     * @throws ShortNrPatternParseException
     */
    private static function analyzeTypeNode(TypeNodeInterface $typeNode): AnalyzerResult
    {
        // Get the type and analyze it
        $type = $typeNode->getType();
        $typeAnalysis = TypeAnalyzer::analyzeType($type);

        // No literals for type nodes
        $literals = [];

        // Adjust min/max based on optionality
        $minLen = $typeNode->isOptional() ? 0 : $typeAnalysis->getMinLen();
        $maxLen = $typeAnalysis->getMaxLen();

        // Types don't have prefix/suffix (those come from literals)
        $prefix = null;
        $suffix = null;

        return new AnalyzerResult(
            minLen: $minLen,
            maxLen: $maxLen,
            literals: $literals,
            allowedChars: $typeAnalysis->getAllowedChars(),
            prefix: $prefix,
            suffix: $suffix
        );
    }

    /**
     * @param NodeTreeInterface $node
     * @return AnalyzerResult
     * @throws ShortNrPatternException
     */
    private static function analyzeTreeNode(NodeTreeInterface $node): AnalyzerResult
    {
        $minLen = 0;
        $maxLen = 0;
        $allowedChars = [];
        $literals = [];

        $firstChild = null;
        $lastChild = null;
        foreach ($node->getChildren() as $child) {
            $lastChild = $childAnalysis = self::analyzeNode($child);
            $firstChild ??= $childAnalysis;

            // Accumulate lengths
            $minLen += $childAnalysis->getMinLen();
            $maxLen += $childAnalysis->getMaxLen();
            // Merge allowed chars
            $allowedChars = $allowedChars + $childAnalysis->getAllowedChars();
            foreach ($childAnalysis->getLiterals() as $literalText => $isRequired) {
                // allow to overwrite only when current value is not already required
                if (($literals[$literalText] ?? false) !== true) {
                    $literals[$literalText] = $isRequired;
                }
            }
        }

        return new AnalyzerResult(
            minLen: $minLen,
            maxLen: $maxLen,
            literals: $literals,
            allowedChars: $allowedChars,
            prefix: $firstChild?->getPrefix(),
            suffix: $lastChild?->getSuffix()
        );
    }

    /**
     * @param LiteralNodeInterface $node
     * @return AnalyzerResult
     */
    private static function analyzeLiteralNode(LiteralNodeInterface $node): AnalyzerResult
    {
        $text = $node->getText();
        $isOptional = $node->isOptional();
        $literalTextLength = strlen($text);
        $allowedChars = [];
        for ($i = 0; $i < $literalTextLength; $i++) {
            $allowedChars[ord($text[$i])] = true;
        }

        return new AnalyzerResult(
            minLen: $isOptional ? 0 : $literalTextLength,
            maxLen: $isOptional ? null : $literalTextLength,
            literals: array_fill_keys([$text], $isOptional),
            allowedChars: $allowedChars,
            // in a literal the literal text is the prefix and the suffix at the same time
            prefix: $text,
            suffix: $text
        );
    }
}
