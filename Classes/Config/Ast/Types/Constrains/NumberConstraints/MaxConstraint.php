<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains\NumberConstraints;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\TypeConstraint;

class MaxConstraint implements TypeConstraint
{
    public function getName(): string
    {
        return 'max';
    }

    /**
     * @inheritDoc
     */
    public function parseValue(mixed $value, mixed $constraintValue): mixed
    {
        if ($value === null) {
            return null; // Let default constraint handle this
        }

        $intValue = (int)$value;
        $maxValue = (int)$constraintValue;

        if ($intValue > $maxValue) {
            throw new \InvalidArgumentException("Value $intValue exceeds maximum $maxValue");
        }

        return $intValue;
    }

    /**
     * @inheritDoc
     */
    public function serialize(mixed $value, mixed $constraintValue): mixed
    {
        // Validation happens during parsing, just return the value for serialization
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function modifyPattern(string $basePattern, mixed $constraintValue): string
    {
        // Convert greedy \d+ to bounded greedy \d{1,n} based on max value
        if ($basePattern === '\d+') {
            $maxValue = (int)$constraintValue;
            $maxDigits = strlen((string)$maxValue);
            return '\d{1,' . $maxDigits . '}'; // Bounded but still greedy within bounds
        }
        
        return $basePattern; // No modification for other patterns
    }

    /**
     * @inheritDoc
     */
    public function capsGreediness(): bool
    {
        return true; // Max constraint caps greediness by limiting digits
    }
}
