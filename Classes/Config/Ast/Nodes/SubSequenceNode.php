<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Nodes;

use CPSIT\ShortNr\Exception\ShortNrPatternParseException;

final class SubSequenceNode extends SequenceNode
{
    /**
     * @return string
     */
    protected function generateRegex(): string
    {
        // Make the entire subsequence optional by default
        return '(?:' . parent::generateRegex() . ')?';
    }

    /**
     * Generate output for this subsequence.
     * Implements all-or-nothing semantics: ALL required groups within must have values
     * for the subsequence to be included.
     */
    public function generate(array $values): string
    {
        // First, check if ALL required groups in this subsequence have values
        if (!$this->canSatisfyAllRequirements($values)) {
            return '';
        }

        // All requirements can be satisfied, generate the full subsequence
        return parent::generate($values);
    }

    /**
     * Check if all required elements in this subsequence can be satisfied.
     * This implements the "all-or-nothing" rule for subsequences.
     */
    private function canSatisfyAllRequirements(array $values): bool
    {
        foreach ($this->children as $child) {
            if ($child instanceof GroupNode) {
                // Check if this required group has a value
                $groupName = $child->getName();
                if (!isset($values[$groupName])) {
                    // Required group has no value - subsequence cannot be satisfied
                    return false;
                }
            } elseif ($child instanceof SubSequenceNode) {
                // Nested subsequences are optional, so they don't block satisfaction
                // But we still check if they CAN be satisfied for proper generation
                // (they will handle their own all-or-nothing logic)
                continue;
            } elseif ($child instanceof LiteralNode) {
                // Literals are always satisfiable
                continue;
            } elseif ($child instanceof SequenceNode) {
                // Check if the sequence can be satisfied
                if (!$this->canSequenceBeSatisfied($child, $values)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if a sequence node can be satisfied with the given values.
     */
    private function canSequenceBeSatisfied(SequenceNode $sequence, array $values): bool
    {
        foreach ($sequence->getChildren() as $child) {
            if ($child instanceof GroupNode) {
                $groupName = $child->getName();
                if (!isset($values[$groupName])) {
                    return false;
                }
            } elseif ($child instanceof SequenceNode && !($child instanceof SubSequenceNode)) {
                // Recursively check nested sequences (but not subsequences which are optional)
                if (!$this->canSequenceBeSatisfied($child, $values)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * SubSequence nodes are always optional
     */
    public function isOptional(): bool
    {
        return true; // Breaks the parent delegation chain
    }

    /**
     * @return void
     * @throws ShortNrPatternParseException
     */
    public function validateTreeContext(): void
    {
        if (empty($this->children)) {
            throw new ShortNrPatternParseException(
                "Empty optional subsequence '()' is not allowed. Optional sections must contain at least one element (group or literal).",
                'unknown'
            );
        }

        parent::validateTreeContext();
    }

    public function getNodeType(): string
    {
        return 'subsequence';
    }
}
