<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\TypeConstraint;

class ContainsConstraint implements TypeConstraint
{
    public function getName(): string
    {
        return 'contains';
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
        $needle = $this->unescapeString((string)$constraintValue);

        if (!str_contains($stringValue, $needle)) {
            throw new \InvalidArgumentException("String '$stringValue' does not contain '$needle'");
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
