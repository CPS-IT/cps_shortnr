<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer\Type;

use CPSIT\ShortNr\Config\Ast\Types\TypeInterface;

class TypeAnalyzer
{
    /**
     * Analyze a type to determine its characteristics for heuristic matching
     * Let the type itself provide the information
     */
    public static function analyzeType(TypeInterface $type): TypeAnalyzerResult
    {
        // Get the base pattern to analyze
        $pattern = $type->getConstrainedPattern();

        // Parse allowed characters from the pattern
        $allowedChars = self::parseAllowedCharsFromPattern($pattern);

        // Parse length constraints from the pattern
        [$minLen, $maxLen] = self::parseLengthFromPattern($pattern);

        // Check if type can be empty (based on pattern)
        $canBeEmpty = self::canPatternMatchEmpty($pattern);

        return new TypeAnalyzerResult(
            minLen: $minLen,
            maxLen: $maxLen,
            allowedChars: $allowedChars,
            canBeEmpty: $canBeEmpty
        );
    }

    /**
     * Parse allowed characters from a regex pattern
     */
    private static function parseAllowedCharsFromPattern(string $pattern): array
    {
        $allowedChars = [];

        // Match character classes like [^/], [a-zA-Z0-9], \d, etc.
        if (preg_match('/\[([^\]]+)\]/', $pattern, $matches)) {
            $charClass = $matches[1];

            // Handle negated character classes [^...]
            if (str_starts_with($charClass, '^')) {
                // For negated classes, allow all printable ASCII except specified
                for ($i = 32; $i < 127; $i++) {
                    $allowedChars[$i] = true;
                }

                // Remove excluded characters
                $excluded = substr($charClass, 1);
                for ($i = 0; $i < strlen($excluded); $i++) {
                    unset($allowedChars[ord($excluded[$i])]);
                }
            } else {
                // Parse positive character class
                $allowedChars = self::parseCharacterClass($charClass);
            }
        } elseif (str_contains($pattern, '\d')) {
            // Digit pattern
            for ($i = ord('0'); $i <= ord('9'); $i++) {
                $allowedChars[$i] = true;
            }
        } elseif (str_contains($pattern, '\w')) {
            // Word characters
            for ($i = ord('a'); $i <= ord('z'); $i++) {
                $allowedChars[$i] = true;
            }
            for ($i = ord('A'); $i <= ord('Z'); $i++) {
                $allowedChars[$i] = true;
            }
            for ($i = ord('0'); $i <= ord('9'); $i++) {
                $allowedChars[$i] = true;
            }
            $allowedChars[ord('_')] = true;
        } else {
            // Default: be permissive
            for ($i = 32; $i < 127; $i++) {
                $allowedChars[$i] = true;
            }
        }

        return $allowedChars;
    }

    /**
     * Parse a character class like "a-zA-Z0-9_-"
     */
    private static function parseCharacterClass(string $charClass): array
    {
        $allowedChars = [];
        $len = strlen($charClass);

        for ($i = 0; $i < $len; $i++) {
            if ($i + 2 < $len && $charClass[$i + 1] === '-') {
                // Range like a-z
                $start = ord($charClass[$i]);
                $end = ord($charClass[$i + 2]);
                for ($j = $start; $j <= $end; $j++) {
                    $allowedChars[$j] = true;
                }
                $i += 2;
            } else {
                // Single character
                $allowedChars[ord($charClass[$i])] = true;
            }
        }

        return $allowedChars;
    }

    /**
     * Parse length constraints from pattern quantifiers
     */
    private static function parseLengthFromPattern(string $pattern): array
    {
        // Look for quantifiers like {1,10}, {3}, +, *, ?
        if (preg_match('/\{(\d+),(\d+)\}/', $pattern, $matches)) {
            // {min,max}
            return [(int)$matches[1], (int)$matches[2]];
        } elseif (preg_match('/\{(\d+)\}/', $pattern, $matches)) {
            // {exact}
            $len = (int)$matches[1];
            return [$len, $len];
        } elseif (str_contains($pattern, '+')) {
            // One or more
            return [1, null];
        } elseif (str_contains($pattern, '*')) {
            // Zero or more
            return [0, null];
        } elseif (str_contains($pattern, '?')) {
            // Zero or one
            return [0, 1];
        }

        // Default: one or more
        return [1, null];
    }

    /**
     * Check if pattern can match empty string
     */
    private static function canPatternMatchEmpty(string $pattern): bool
    {
        return str_contains($pattern, '*') ||
            str_contains($pattern, '?') ||
            str_contains($pattern, '{0');
    }
}
