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
    private const REGEX_LOOK_AHEAD = '/\[(\^[^]]*)]/';
    private const REGEX_NEGATIVE_LOOK_AHEAD = '/(\[[^]]+])(\+)/';

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

    public function parseValue(mixed $value): mixed
    {
        // Validate raw input before applying constraints
        if (!is_scalar($value) || empty($value)) {
            throw new InvalidArgumentException('Value must be scalar for str type, got: \'' . gettype($value).'\'');
        }
        
        return parent::parseValue((string)$value);
    }

    /**
     * @throws ShortNrPatternConstraintException
     */
    public function serialize(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ShortNrPatternConstraintException(
                'Expected string value, got ' . gettype($value),
                'unknown',
                $value,
                'type_validation'
            );
        }
        return parent::serialize($value);
    }

    public function applyBoundary(string $pattern, ?string $boundary): string
    {
        if ($boundary === null) {
            return $pattern;
        }

        $escapedBoundary = preg_quote($boundary, '/');

        // String type knows its pattern is [^/]+ and how to modify it
        if (strlen($boundary) === 1) {
            // For single char: modify character class
            if (preg_match(self::REGEX_LOOK_AHEAD, $pattern, $matches)) {
                $charClass = $matches[1];
                if (!str_contains($charClass, $escapedBoundary)) {
                    $newCharClass = $charClass . $escapedBoundary;
                    return str_replace('[' . $charClass . ']', '[' . $newCharClass . ']', $pattern);
                }
            }
        } else {
            // For multi-char: use negative lookahead
            $lookahead = '(?!' . $escapedBoundary . ')';
            return preg_replace(self::REGEX_NEGATIVE_LOOK_AHEAD, '(?:' . $lookahead . '$1)$2', $pattern);
        }

        return $pattern;
    }

    /**
     * @inheritDoc
     */
    public function isGreedy(): bool
    {
        // v1.0: String type is always greedy, constraints don't affect greediness
        return true;
    }
}
