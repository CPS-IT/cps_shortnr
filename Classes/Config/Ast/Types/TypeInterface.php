<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types;

use InvalidArgumentException;

interface TypeInterface
{
    /**
     * Get the type name (e.g., 'int', 'slug', 'string')
     * @return string[]
     */
    public function getName(): array;

    /**
     * Get the regex pattern this type matches.
     * This is used by AST nodes to build the final regex.
     */
    public function getPattern(): string;

    /**
     * Parse a string value into the appropriate PHP type.
     *
     * @param string $value The raw matched string
     * @param array<string, string> $constraints Constraints from pattern definition
     * @return mixed The parsed value
     * @throws InvalidArgumentException When value doesn't meet constraints
     */
    public function parseValue(mixed $value, array $constraints = []): mixed;

    /**
     * Serialize a PHP value back to string for URL generation.
     *
     * @param mixed $value The PHP value
     * @param array<string, string> $constraints Constraints from pattern definition
     * @return string The serialized string
     * @throws InvalidArgumentException When value doesn't meet constraints
     */
    public function serialize(mixed $value, array $constraints = []): string;

    /**
     * Get character classes used by this type (for heuristic building).
     */
    public function getCharacterClasses(): array;

    /**
     * Get regex pattern with constraints applied.
     * Returns non-greedy patterns when capping constraints are present.
     */
    public function getConstrainedPattern(array $constraints = []): string;

    /**
     * Check if this type with given constraints is greedy.
     * Greedy types consume as much as possible unless capped by constraints.
     */
    public function isGreedy(array $constraints = []): bool;
}
