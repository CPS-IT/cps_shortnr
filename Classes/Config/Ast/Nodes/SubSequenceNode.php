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
        return '(?:' . parent::generateRegex() . ')?';
    }

    /**
     * @param array $values
     * @return string
     */
    public function generate(array $values): string
    {
        // Check if any group in this optional section has a value
        $hasAnyValue = false;
        foreach ($this->children as $child) {
            if ($child instanceof GroupNode && isset($values[$child->getName()])) {
                $hasAnyValue = true;
                break;
            }
        }

        if (!$hasAnyValue) {
            return '';
        }

        $result = '';
        foreach ($this->children as $child) {
            $result .= $child->generate($values);
        }
        return $result;
    }

    /**
     * Sub sequence nodes are always Optional ... ( ... )
     *
     * @return bool
     */
    public function hasOptional(): bool
    {
        return true;
    }

    protected function isLocallyOptional(): bool
    {
        return true; // SubSequence nodes are always optional
    }

    public function validateTreeContext(): void
    {
        // Check for empty subsequence
        if (empty($this->children)) {
            throw new ShortNrPatternParseException(
                "Empty optional subsequence '()' is not allowed. Optional sections must contain at least one element (group or literal).",
                'unknown' // Pattern context would need to be passed from parser
            );
        }
        
        // Call parent validation
        parent::validateTreeContext();
    }

    public function getNodeType(): string
    {
        return 'subsequence';
    }
}
