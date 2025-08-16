<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\DefaultConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints\ContainsConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints\EndsWithConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints\MaxLengthConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints\MinLengthConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints\StartsWithConstraint;
use CPSIT\ShortNr\Exception\ShortNrPatternConstraintException;

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
        return parent::parseValue((string)$value, $constraints);
    }

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
        // String is inherently greedy ([^/]+), but delegate to constraints to check if they cap it
        foreach ($constraints as $constraintName => $constraintValue) {
            $constraint = $this->getConstraint($constraintName);
            if ($constraint?->capsGreediness()) {
                // DEBUG: Show when non-greedy due to constraint
                // var_dump("StringType made non-greedy by constraint: $constraintName");
                return false; // Any capping constraint makes it non-greedy
            }
        }
        return true; // No capping constraints, remains greedy
    }
}
