<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\TypeConstraint;
use InvalidArgumentException;

class StartsWithConstraint implements TypeConstraint
{
    public function getName(): string
    {
        return 'startsWith';
    }

    /**
     * @inheritDoc
     */
    public function parseValue(mixed $value, mixed $constraintValue): mixed
    {
        if ($value === null) {
            return null; // Let default constraint handle this
        }

        $stringValue = (string)$value;
        $prefix = $this->unescapeString((string)$constraintValue);

        if (!str_starts_with($stringValue, $prefix)) {
            throw new InvalidArgumentException("String '$stringValue' does not start with '$prefix'");
        }

        return $stringValue;
    }

    /**
     * @inheritDoc
     */
    public function serialize(mixed $value, mixed $constraintValue): mixed
    {
        return $value;
    }

    public function modifyPattern(string $basePattern, mixed $constraintValue): string
    {
        // This constraint doesn't modify pattern width
        return $basePattern;
    }

    /**
     * @inheritDoc
     */
    public function capsGreediness(): bool
    {
        return false; // StartsWith constraint doesn't cap greediness
    }

    private function unescapeString(string $value): string
    {
        // Remove surrounding quotes if present
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }

        // Unescape common sequences
        return str_replace(['\"', '\\\\'], ['"', '\\'], $value);
    }
}
