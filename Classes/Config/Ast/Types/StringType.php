<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\DefaultConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints\ContainsConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints\EndsWithConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints\MaxLengthConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints\MinLengthConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints\StartsWithConstraint;
use CPSIT\ShortNr\Exception\ShortNrPatternConstraintException;
use InvalidArgumentException;

final class StringType extends Type
{
    public function __construct()
    {
        $this->name = ['str', 'string'];
        $this->pattern = '[^\/]+';
        $this->characterClasses = ['a-zA-Z0-9', '_', '-', '.'];

        $this->registerConstraint(
            new DefaultConstraint(),
            new MinLengthConstraint(),
            new MaxLengthConstraint(),
            new ContainsConstraint(),
            new StartsWithConstraint(),
            new EndsWithConstraint()
        );
    }

    public function parseValue(mixed $value, array $constraints = []): mixed
    {
        // Validate raw input before applying constraints
        if (!is_scalar($value)) {
            throw new InvalidArgumentException("Value must be scalar for str type, got: " . gettype($value));
        }
        
        return parent::parseValue((string)$value, $constraints);
    }

    /**
     * @throws ShortNrPatternConstraintException
     */
    public function serialize(mixed $value, array $constraints = []): string
    {
        if (!is_string($value)) {
            throw new ShortNrPatternConstraintException(
                'Expected string value, got ' . gettype($value),
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
        // v1.0: String type is always greedy, constraints don't affect greediness
        return true;
    }
}
