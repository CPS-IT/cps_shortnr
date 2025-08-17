<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

class SequenceNode extends NestedAstNode
{
    protected function generateRegex(): string
    {
        $regex = '';
        foreach ($this->children as $i => $child) {
            $nextLiteral = $this->findNextLiteralBoundary($i + 1);
            $chunk = $child->toRegex();

            // Only post-process greedy groups followed by a literal
            // BUT: Skip boundary injection entirely in SubSequences (all-or-nothing logic)
            $isInSubSequence = ($this instanceof SubSequenceNode);
            
            if ($child instanceof GroupNode && $child->isGreedy() && $nextLiteral !== null && !$isInSubSequence) {
                $chunk = $this->injectBoundary($chunk, $nextLiteral);
            }

            $regex .= $chunk;
        }

        return $regex;
    }

    private function findNextLiteralBoundary(int $startIndex): ?string
    {
        // First, look for literals in current sequence after startIndex
        $literal = $this->nextLiteralAmong($this->children, $startIndex);
        if ($literal !== null) {
            return $literal;
        }
        
        // If no literal found in current sequence and we're at the end,
        // look up the parent hierarchy to find next boundary
        if ($startIndex >= count($this->children)) {
            return $this->findNextLiteralInParent();
        }
        
        return null;
    }
    
    private function findNextLiteralInParent(): ?string
    {
        $parent = $this->getParent();
        if (!($parent instanceof NestedAstNode)) {
            return null;
        }
        
        // Find our position in parent's children
        $parentChildren = $parent->getChildren();
        $ourIndex = array_search($this, $parentChildren, true);
        
        if ($ourIndex === false) {
            return null;
        }
        
        // Look for literal after our position in parent
        for ($i = $ourIndex + 1; $i < count($parentChildren); $i++) {
            if ($parentChildren[$i] instanceof LiteralNode) {
                return $parentChildren[$i]->getText();
            }
            
            if ($parentChildren[$i] instanceof SubSequenceNode) {
                $subChildren = $parentChildren[$i]->getChildren();
                if (!empty($subChildren) && $subChildren[0] instanceof LiteralNode) {
                    return $subChildren[0]->getText();
                }
                break;
            }
            
            if ($parentChildren[$i] instanceof GroupNode) {
                break;
            }
        }
        
        // If still no literal found, recurse up to grandparent
        if ($parent instanceof SequenceNode) {
            return $parent->findNextLiteralInParent();
        }
        
        return null;
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
        // Make the pattern non-greedy and add lookahead
        $result = preg_replace('/(\[[\^]?[^\]]+\]\+)/', '$1?', $groupRegex);
        return preg_replace('/\)$/', "(?=$escaped|$))", $result, 1);
    }


    public function generate(array $values): string
    {
        $result = '';
        foreach ($this->children as $child) {
            $result .= $child->generate($values);
        }
        return $result;
    }

    public function hasOptional(): bool
    {
        foreach ($this->children as $child) {
            if ($child->hasOptional()) {
                return true;
            }
        }
        return false;
    }

    public function getNodeType(): string
    {
        return 'sequence';
    }
}
