<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains\NumberConstraints;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\TypeConstraint;

class MinConstraint implements TypeConstraint
{
    public function getName(): string
    {
        return 'min';
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
        $minValue = (int)$constraintValue;

        if ($intValue < $minValue) {
            throw new \InvalidArgumentException("Value $intValue is below minimum $minValue");
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
}
