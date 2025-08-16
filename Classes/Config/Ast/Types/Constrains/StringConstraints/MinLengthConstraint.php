<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Types\Constrains\StringConstraints;

use CPSIT\ShortNr\Config\Ast\Types\Constrains\TypeConstraint;

class MinLengthConstraint implements TypeConstraint
{
    public function getName(): string
    {
        return 'minLen';
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
        $minLength = (int)$constraintValue;

        if (strlen($stringValue) < $minLength) {
            throw new \InvalidArgumentException("String length " . strlen($stringValue) . " is below minimum $minLength");
        }

        return $stringValue;
    }

    /**
     * @inheritDoc
     */
    public function serialize(mixed $value, mixed $constraintValue): mixed
    {
        // Validation happens during parsing, just return the value for serialization
        return $value;
    }

    public function modifyPattern(string $basePattern, mixed $constraintValue): string
    {
        var_dump("MinLengthConstraint::modifyPattern - basePattern: $basePattern, constraintValue: $constraintValue");
        
        // Modify pattern to enforce minimum length if pattern has bounds
        if (preg_match('/\{(\d+),(\d+)\}([?]?)$/', $basePattern, $matches)) {
            var_dump("MinLength regex matches:", $matches);
            $currentMin = $matches[1]; 
            $maxLen = $matches[2];
            $nonGreedy = $matches[3] ?? '';
            $minLen = max((int)$constraintValue, (int)$currentMin);
            $result = preg_replace('/\{\d+,\d+\}[?]?$/', '{' . $minLen . ',' . $maxLen . '}' . $nonGreedy, $basePattern);
            var_dump("MinLength result: $result");
            return $result;
        }
        var_dump("MinLength no match, returning: $basePattern");
        return $basePattern;
    }

    /**
     * @inheritDoc
     */
    public function capsGreediness(): bool
    {
        return false; // MinLen constraint doesn't cap greediness
    }
}
