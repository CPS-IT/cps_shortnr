<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Validation;

use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\AstNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\LiteralNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\NestedNodeInterface;
use CPSIT\ShortNr\Config\Ast\Nodes\Interfaces\TypeNodeInterface;
use CPSIT\ShortNr\Exception\ShortNrPatternException;

/**
 * Validates greediness rules for adjacent groups in sequences.
 * 
 * Rules:
 * 1. No two greedy groups can be adjacent in the same sequence
 * 2. Literals break adjacency 
 * 3. SubSequences break adjacency
 * 4. Constrained (non-greedy) groups can be adjacent to greedy groups
 */
final class GreedyValidator implements ValidatorInterface
{
    /**
     * @throws ShortNrPatternException
     */
    public function validate(AstNodeInterface $astNode): void
    {
        $this->validateNode($astNode);
    }

    /**
     * @throws ShortNrPatternException
     */
    private function validateNode(AstNodeInterface $node): void
    {
        // Recursively validate children first
        if ($node instanceof NestedNodeInterface) {
            foreach ($node->getChildren() as $child) {
                $this->validateNode($child);
            }
            
            // Then validate this sequence's children for adjacent greediness
            $this->validateSequence($node->getChildren());
        }
    }

    /**
     * @param AstNodeInterface[] $children
     * @throws ShortNrPatternException
     */
    private function validateSequence(array $children): void
    {
        $previousGreedyGroup = null;
        
        foreach ($children as $child) {
            if ($child instanceof TypeNodeInterface) {
                if ($child->isGreedy()) {
                    if ($previousGreedyGroup !== null) {
                        throw new ShortNrPatternException(
                            "Adjacent greedy groups '" . $previousGreedyGroup->getName() . "' and '" . $child->getName() . "' are forbidden. " .
                            "Add a literal separator, use constraints to make one non-greedy, or wrap in SubSequences."
                        );
                    }
                    $previousGreedyGroup = $child;
                } else {
                    // Non-greedy group doesn't violate adjacency
                    $previousGreedyGroup = null;
                }
            } elseif ($child instanceof LiteralNodeInterface) {
                // Literals break adjacency, for now v1
                $previousGreedyGroup = null;
            }
        }
    }
}
