<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

class SequenceNode extends NestedAstNode
{
    /**
     * Override generateRegex to handle greediness conflicts
     */
    protected function generateRegex(): string
    {
        $regex = '';
        $childrenCount = count($this->children);
        
        for ($i = 0; $i < $childrenCount; $i++) {
            $current = $this->children[$i];
            $next = $i + 1 < $childrenCount ? $this->children[$i + 1] : null;
            
            // Check for greediness conflicts and fail fast if found
            if ($current instanceof GroupNode && $next instanceof GroupNode) {
                if ($this->shouldMakeNonGreedy($current, $next)) {
                    throw new \CPSIT\ShortNr\Exception\ShortNrPatternException(
                        "Adjacent greedy groups detected: '{$current->getName()}' and '{$next->getName()}'. " .
                        "Add constraints to limit greediness or separate with literals."
                    );
                }
            } elseif ($current instanceof SubSequenceNode && $next instanceof GroupNode) {
                $firstGroupInSub = $this->getFirstGroup($current);
                if ($firstGroupInSub && $this->shouldMakeNonGreedy($firstGroupInSub, $next)) {
                    throw new \CPSIT\ShortNr\Exception\ShortNrPatternException(
                        "Adjacent greedy groups detected: '{$firstGroupInSub->getName()}' and '{$next->getName()}'. " .
                        "Add constraints to limit greediness or separate with literals."
                    );
                }
            } elseif ($current instanceof GroupNode && $next instanceof SubSequenceNode) {
                $firstGroupInSub = $this->getFirstGroup($next);
                if ($firstGroupInSub && $this->shouldMakeNonGreedy($current, $firstGroupInSub)) {
                    throw new \CPSIT\ShortNr\Exception\ShortNrPatternException(
                        "Adjacent greedy groups detected: '{$current->getName()}' and '{$firstGroupInSub->getName()}'. " .
                        "Add constraints to limit greediness or separate with literals."
                    );
                }
            }
            
            $regex .= $current->toRegex();
        }
        
        return $regex;
    }

    /**
     * Determine if current group should be made non-greedy to avoid conflicts
     */
    private function shouldMakeNonGreedy(GroupNode $current, GroupNode $next): bool
    {
        // If either group is already non-greedy due to constraints, no need to modify
        if (!$current->isGreedy() || !$next->isGreedy()) {
            return false;
        }
        
        // Both are int types that could conflict
        if ($current->getType() === 'int' && $next->getType() === 'int') {
            return true;
        }
        
        // Both are string types that could conflict  
        if ($current->getType() === 'str' && $next->getType() === 'str') {
            return true;
        }
        
        // String followed by int can conflict (string pattern [^\/]+ can match digits)
        if ($current->getType() === 'str' && $next->getType() === 'int') {
            return true;
        }
        
        return false;
    }

    /**
     * Get the first GroupNode in a SubSequence
     */
    private function getFirstGroup(SubSequenceNode $subSequence): ?GroupNode
    {
        foreach ($subSequence->getChildren() as $child) {
            if ($child instanceof GroupNode) {
                return $child;
            }
        }
        return null;
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
