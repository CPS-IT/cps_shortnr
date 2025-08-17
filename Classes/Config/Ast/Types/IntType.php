<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\DefaultConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\NumberConstraints\MaxConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\NumberConstraints\MinConstraint;
use CPSIT\ShortNr\Exception\ShortNrPatternConstraintException;
use InvalidArgumentException;

final class IntType extends Type
{
    public function __construct()
    {
        $this->name = ['int', 'integer'];
        $this->pattern = '\d+';
        $this->characterClasses = ['0-9'];

        $this->registerConstraint(
            new DefaultConstraint(),
            new MinConstraint(),
            new MaxConstraint()
        );
    }

    /**
     * @param mixed $value
     * @param array $constraints
     * @return mixed
     */
    public function parseValue(mixed $value, array $constraints = []): mixed
    {
        // Apply constraints first (including defaults) 
        $value = parent::parseValue($value, $constraints);
        
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

    /**
     * @param mixed $value
     * @param array $constraints
     * @return string
     * @throws ShortNrPatternConstraintException
     */
    public function serialize(mixed $value, array $constraints = []): string
    {
        if (!is_int($value)) {
            throw new ShortNrPatternConstraintException(
                'Expected integer value, got ' . gettype($value),
                'unknown',
                $value,
                'type_validation'
            );
        }
        return parent::serialize($value, $constraints);
    }

    /**
     * @inheritDoc
     */
    public function isGreedy(array $constraints = []): bool
    {
        // v1.0: Int type is always greedy, constraints don't affect greediness
        return true;
    }
}
