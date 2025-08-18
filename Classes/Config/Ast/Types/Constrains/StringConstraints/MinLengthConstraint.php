<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\BaseConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\Interfaces\RefinementConstraintInterface;
use InvalidArgumentException;

class MinLengthConstraint extends BaseConstraint implements RefinementConstraintInterface
{
    public const NAME = 'minLen';

    /**
     * @inheritDoc
     */
    public function parseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null; // Let default constraint handle this
        }

        $stringValue = (string)$value;
        $minLength = (int)$this->value;

        if (strlen($stringValue) < $minLength) {
            throw new InvalidArgumentException("String length " . strlen($stringValue) . " is below minimum $minLength");
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
        return false; // MinLen constraint doesn't cap greediness
    }
}
