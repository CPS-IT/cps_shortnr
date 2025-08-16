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

        foreach ($children as $i => $child) {
            $nextLiteral = $this->nextLiteralAmong($children, $i + 1);
            $chunk = $child->toRegex();

            // Only post-process greedy groups followed by a literal
            if ($child instanceof GroupNode && $child->isGreedy() && $nextLiteral !== null) {
                $chunk = $this->injectBoundary($chunk, $nextLiteral);
            }

            $regex .= $chunk;
        }

        return $regex;
    }

    protected function wrapSequence(string $regex): string
    {
        return $regex; // base sequence – no wrapping
    }

    private function nextLiteralAmong(array $children, int $start): ?string
    {
        for ($j = $start; $j < count($children); $j++) {
            if ($children[$j] instanceof LiteralNode) {
                return $children[$j]->getText();
            }
            
            // Check if it's a SubSequence - might contain a literal at start
            if ($children[$j] instanceof SubSequenceNode) {
                $subChildren = $children[$j]->getChildren();
                if (!empty($subChildren) && $subChildren[0] instanceof LiteralNode) {
                    return $subChildren[0]->getText();
                }
                // SubSequence doesn't start with literal, stop searching
                break;
            }
            
            // stop at the first group
            if ($children[$j] instanceof GroupNode) {
                break;
            }
        }
        
        return null;
    }

    private function injectBoundary(string $groupRegex, string $literal): string
    {
        $escaped = preg_quote($literal, '/');
        // Add lookahead before the final closing parenthesis
        return preg_replace('/\)$/', "(?={$escaped}|$))", $groupRegex, 1);
    }
}