<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains\NumberConstraints;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\BaseConstraint;
use CPSIT\ShortNr\Config\Ast\Types\Constrains\Interfaces\RefinementConstraintInterface;
use InvalidArgumentException;

class MinConstraint extends BaseConstraint implements RefinementConstraintInterface
{
    public const NAME = 'min';

    /**
     * @inheritDoc
     */
    public function parseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null; // Let default constraint handle this
        }

        $intValue = (int)$value;
        $minValue = (int)$this->value;

        if ($intValue < $minValue) {
            throw new InvalidArgumentException("Value $intValue is below minimum $minValue");
        }

        return $intValue;
    }

    /**
     * @inheritDoc
     */
    public function serialize(mixed $value): mixed
    {
        // Validation happens during parsing, just return the value for serialization
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function modifyPattern(string $basePattern): string
    {
        // Min constraint doesn't cap/limit pattern width
        return $basePattern;
    }

    /**
     * @inheritDoc
     */
    public function capsGreediness(): bool
    {
        return false; // Min constraint doesn't cap greediness
    }
}
