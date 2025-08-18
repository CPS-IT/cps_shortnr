<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\BaseConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\Interfaces\BoundingConstraintInterface;
use InvalidArgumentException;

class MaxLengthConstraint extends BaseConstraint implements BoundingConstraintInterface
{
    public const NAME = 'maxLen';

    /**
     * @inheritDoc
     */
    public function parseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null; // Let default constraint handle this
        }

        $stringValue = (string)$value;
        $maxLength = (int)$this->value;

        if (strlen($stringValue) > $maxLength) {
            throw new InvalidArgumentException("String length " . strlen($stringValue) . " exceeds maximum $maxLength");
        }

        return $stringValue;
    }

    /**
     * @inheritDoc
     */
    public function serialize(mixed $value): mixed
    {
        // Validation happens during parsing, just return the value for serialization
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function modifyPattern(string $basePattern): string
    {
        // v1.0: Constraints don't modify patterns, validation-only
        return $basePattern;
    }

    /**
     * @inheritDoc
     */
    public function capsGreediness(): bool
    {
        return true; // MaxLen constraint caps greediness by limiting length
    }
}
