<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types;

use CPSIT\ShortNr\Exception\ShortNrPatternTypeException;
use InvalidArgumentException;

interface TypeInterface
{
    /**
     * Get the name of this type. (include all aliases)
     *
     * Get the type name (e.g., 'int', 'slug', 'string')
     * @return string[]
     */
    public function getName(): array;

    /**
     * @return string return the first name in the name list
     * @throws ShortNrPatternTypeException
     */
    public function getDefaultName(): string;

    /**
     * Get the regex pattern this type matches.
     * This is used by AST nodes to build the final regex.
     */
    public function getPattern(): string;

    /**
     * @return array<string, string> Type-specific constraints arguments
     */
    public function getConstraintsArgument(): array;

    /**
     * @internal
     * @param array<string, string> $constraintArguments Type-specific constraints arguments
     * @return $this
     */
    public function setConstraintArguments(array $constraintArguments): static;

    /**
     * Parse a string value into the appropriate PHP type.
     *
     * @param string $value The raw matched string
     * @return mixed The parsed value
     * @throws InvalidArgumentException When value doesn't meet constraints
     */
    public function parseValue(mixed $value): mixed;

    /**
     * Serialize a PHP value back to string for URL generation.
     *
     * @param mixed $value The PHP value
     * @return string The serialized string
     * @throws InvalidArgumentException When value doesn't meet constraints
     */
    public function serialize(mixed $value): string;

    /**
     * Get character classes used by this type (for heuristic building).
     */
    public function getCharacterClasses(): array;

    /**
     * Get regex pattern with constraints applied.
     * Returns non-greedy patterns when capping constraints are present.
     */
    public function getConstrainedPattern(): string;

    /**
     * Check if this type with given constraints is greedy.
     * Greedy types consume as much as possible unless capped by constraints.
     */
    public function isGreedy(): bool;

    /**
     * Apply boundary to pattern for greedy types
     * The type knows HOW to apply boundaries for its specific pattern
     *
     * @internal
     * @param string $pattern The base pattern
     * @param string|null $boundary The boundary string to apply
     * @return string Modified pattern with boundary applied
     */
    public function applyBoundary(string $pattern, ?string $boundary): string;
}
