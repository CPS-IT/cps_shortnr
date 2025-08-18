<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains;

class DefaultConstraint extends BaseConstraint
{
    public const NAME = 'default';

    public function parseValue(mixed $value): mixed
    {
        // Default only applies when value is null/missing
        // If value exists, let other constraints handle validation
        return $value ?? $this->value;
    }

    public function serialize(mixed $value): mixed
    {
        // For serialization, use the actual value (defaults handled during parsing)
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function capsGreediness(): bool
    {
        return false; // Default constraint doesn't cap greediness
    }
}
