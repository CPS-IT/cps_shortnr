<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains;

use InvalidArgumentException;

interface TypeConstraint
{
    /**
     * constant name used in pattern
     * @return string
     */
    public function getName(): string;

    /**
     * Process the value - validate, transform, or apply defaults.
     *
     * @param mixed $value The value to process
     * @param mixed $constraintValue The constraint parameter
     * @return mixed The processed value (maybe same or transformed)
     * @throws InvalidArgumentException When validation fails
     */
    public function parseValue(mixed $value, mixed $constraintValue): mixed;

    /**
     * Modify the builder in-place or return null to skip
     * @param mixed $value
     * @param string $constraintValue
     * @return mixed
     */
    public function serialize(mixed $value, string $constraintValue): mixed;

    /**
     * Modify the regex pattern based on this constraint.
     * Return the original pattern if no modification needed.
     * 
     * @param string $basePattern The base regex pattern from the type
     * @param mixed $constraintValue The constraint parameter value
     * @return string The modified pattern
     */
    public function modifyPattern(string $basePattern, mixed $constraintValue): string;

    /**
     * Whether this constraint caps greediness (makes patterns non-greedy).
     * Used to determine if a type with this constraint should be considered greedy.
     * 
     * @return bool True if this constraint caps/limits greediness
     */
    public function capsGreediness(): bool;
}
