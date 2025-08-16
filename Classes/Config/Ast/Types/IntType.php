<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\DefaultConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\NumberConstraints\MaxConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\NumberConstraints\MinConstraint;
use CPSIT\ShortNr\Exception\ShortNrPatternConstraintException;

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
        // Apply constraints first (including defaults), then convert to int
        $value = parent::parseValue($value, $constraints);
        return (int)$value;
    }

    /**
     * @param mixed $value
     * @param array $constraints
     * @return string
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
        // Int is inherently greedy (\d+), but delegate to constraints to check if they cap it
        foreach ($constraints as $constraintName => $constraintValue) {
            $constraint = $this->getConstraint($constraintName);
            if ($constraint?->capsGreediness()) {
                return false; // Any capping constraint makes it non-greedy
            }
        }
        return true; // No capping constraints, remains greedy
    }
}
