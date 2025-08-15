<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains;

class DefaultConstraint implements TypeConstraint
{
    public function getName(): string
    {
        return 'default';
    }

    public function parseValue(mixed $value, mixed $constraintValue): mixed
    {
        // Default only applies when value is null/missing
        // If value exists, let other constraints handle validation
        return $value ?? $constraintValue;
    }

    public function serialize(mixed $value, mixed $constraintValue): mixed
    {
        // For serialization, use the actual value (defaults handled during parsing)
        return $value;
    }
}
