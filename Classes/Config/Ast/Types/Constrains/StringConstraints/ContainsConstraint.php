<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\BaseConstraint;
use InvalidArgumentException;

class ContainsConstraint extends BaseConstraint
{
    public const NAME = 'contains';

    /**
     * @inheritDoc
     */
    public function parseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null; // Let default constraint handle this
        }

        $stringValue = (string)$value;
        $needle = $this->unescapeString((string)$this->value);

        if (!str_contains($stringValue, $needle)) {
            throw new InvalidArgumentException("String '$stringValue' does not contain '$needle'");
        }

        return $stringValue;
    }

    /**
     * @inheritDoc
     */
    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function capsGreediness(): bool
    {
        return false; // Contains constraint doesn't cap greediness
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
