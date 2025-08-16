<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\RegexGeneration\Strategies;

use CPSIT\ShortNr\Config\Ast\Nodes\GroupNode;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NestedNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\LiteralNode;
use CPSIT\ShortNr\Config\Ast\Nodes\SequenceNode;
use CPSIT\ShortNr\Config\Ast\Nodes\SubSequenceNode;
use CPSIT\ShortNr\Config\Ast\RegexGeneration\RegexGenerationStrategyInterface;

class SequenceRegexStrategy implements RegexGenerationStrategyInterface
{
    public function supports(AstNodeInterface $node): bool
    {
        return $node instanceof SequenceNode && !($node instanceof SubSequenceNode);
    }

    public function generateRegex(AstNodeInterface $node): string
    {
        /** @var NestedNodeInterface $node */
        $regex = $this->generateChildrenRegex($node->getChildren());
        return $this->wrapSequence($regex);
    }

    protected function generateChildrenRegex(array $children): string
    {
        $regex = '';
        $count = count($children);

        var_dump("=== generateChildrenRegex ===");
        var_dump("Children count: " . $count);

        foreach ($children as $i => $child) {
            $nextLiteral = $this->nextLiteralAmong($children, $i + 1);
            $chunk = $child->toRegex();

            var_dump("Child $i: " . get_class($child));
            if ($child instanceof GroupNode) {
                var_dump("  - GroupNode name: " . $child->getName());
                var_dump("  - GroupNode type: " . $child->getType());
                var_dump("  - Is greedy: " . ($child->isGreedy() ? 'YES' : 'NO'));
            }
            if ($child instanceof LiteralNode) {
                var_dump("  - LiteralNode text: '" . $child->getText() . "'");
            }
            var_dump("  - Next literal: " . ($nextLiteral ?? 'NULL'));
            var_dump("  - Original chunk: " . $chunk);

            // Only post-process greedy groups followed by a literal
            if ($child instanceof GroupNode && $child->isGreedy() && $nextLiteral !== null) {
                $original = $chunk;
                $chunk = $this->injectBoundary($chunk, $nextLiteral);
                var_dump("  - BOUNDARY INJECTED: $original -> $chunk");
            }

            $regex .= $chunk;
        }

        var_dump("Final regex: " . $regex);
        return $regex;
    }

    protected function wrapSequence(string $regex): string
    {
        return $regex; // base sequence – no wrapping
    }

    private function nextLiteralAmong(array $children, int $start): ?string
    {
        var_dump("=== nextLiteralAmong ===");
        var_dump("Start index: $start, Total children: " . count($children));
        
        for ($j = $start; $j < count($children); $j++) {
            var_dump("Checking child $j: " . get_class($children[$j]));
            
            if ($children[$j] instanceof LiteralNode) {
                $literal = $children[$j]->getText();
                var_dump("Found literal: '$literal'");
                return $literal;
            }
            
            // Check if it's a SubSequence - might contain a literal at start
            if ($children[$j] instanceof SubSequenceNode) {
                var_dump("Found SubSequence - checking its children");
                $subChildren = $children[$j]->getChildren();
                if (!empty($subChildren) && $subChildren[0] instanceof LiteralNode) {
                    $literal = $subChildren[0]->getText();
                    var_dump("Found literal in SubSequence: '$literal'");
                    return $literal;
                }
                var_dump("SubSequence doesn't start with literal, stopping");
                break;
            }
            
            // stop at the first group
            if ($children[$j] instanceof GroupNode) {
                var_dump("Found GroupNode, stopping");
                break;
            }
        }
        
        var_dump("No literal found, returning null");
        return null;
    }

    private function injectBoundary(string $groupRegex, string $literal): string
    {
        $escaped = preg_quote($literal, '/');
        // Add lookahead before the final closing parenthesis
        return preg_replace('/\)$/', "(?={$escaped}|$))", $groupRegex, 1);
    }
}