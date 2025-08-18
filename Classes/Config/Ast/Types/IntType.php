<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\DefaultConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\NumberConstraints\MaxConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\NumberConstraints\MinConstraint;
use CPSIT\ShortNr\Exception\ShortNrPatternConstraintException;
use InvalidArgumentException;

final class IntType extends Type
{
    protected const DEFAULT_NAME = 'int';
    protected const TYPE_NAMES_ALIASES = ['integer'];

    protected string $pattern = '\d+';
    protected array $characterClasses = ['0-9','-'];

    /**
     * @internal
     * @return string[] return the supported Constraint classes for that Type
     */
    public function getSupportedConstraintClasses(): array
    {
        return [
            MinConstraint::class,
            MaxConstraint::class,
            DefaultConstraint::class
        ];
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function parseValue(mixed $value): mixed
    {
        // Apply constraints first (including defaults) 
        $value = parent::parseValue($value);
        
        // Then validate the processed value
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Value must be numeric for int type, got: " . gettype($value));
        }
        
        // Reject decimal numbers for int type (per compiler-syntax.md:250)
        if (is_string($value) && str_contains($value, '.')) {
            throw new InvalidArgumentException("Value must be numeric for int type, got: " . gettype($value));
        }
        
        return (int)$value;
    }

    public function applyBoundary(string $pattern, ?string $boundary): string
    {
        if ($boundary === null) {
            return $pattern;
        }

        // Int type knows its pattern is \d+ and uses positive lookahead
        $escapedBoundary = preg_quote($boundary, '/');
        return $pattern . '(?=' . $escapedBoundary . '|$)';
    }

    /**
     * @param mixed $value
     * @return string
     * @throws ShortNrPatternConstraintException
     */
    public function serialize(mixed $value): string
    {
        if (!is_int($value)) {
            throw new ShortNrPatternConstraintException(
                'Expected integer value, got ' . gettype($value),
                'unknown',
                $value,
                'type_validation'
            );
        }
        return parent::serialize($value);
    }

    /**
     * @inheritDoc
     */
    public function isGreedy(): bool
    {
        // v1.0: Int type is always greedy, constraints don't affect greediness
        return true;
    }
}
