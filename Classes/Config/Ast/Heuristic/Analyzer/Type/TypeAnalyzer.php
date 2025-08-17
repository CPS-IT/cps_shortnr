<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer\Type;

use CPSIT\ShortNr\Config\Ast\Types\TypeInterface;
use InvalidArgumentException;

class TypeAnalyzer
{
    /**
     * Analyze a type by probing it with test values
     * No knowledge about specific types needed!
     */
    public static function analyzeType(TypeInterface $type): TypeAnalyzerResult
    {
        // Probe for allowed characters
        $allowedChars = self::probeAllowedCharacters($type);

        // Probe for length constraints
        [$minLen, $maxLen] = self::probeLengthConstraints($type);

        // Check if empty string is valid
        $canBeEmpty = self::probeCanBeEmpty($type);

        return new TypeAnalyzerResult(
            minLen: $minLen,
            maxLen: $maxLen,
            allowedChars: $allowedChars,
            canBeEmpty: $canBeEmpty
        );
    }

    /**
     * Probe which characters are allowed by trying them
     */
    private static function probeAllowedCharacters(TypeInterface $type): array
    {
        $allowedChars = [];

        // Test all printable ASCII characters
        for ($i = 32; $i < 127; $i++) {
            $char = chr($i);

            try {
                // Try to parse this single character
                $type->parseValue($char);
                // If no exception, this character is allowed
                $allowedChars[$i] = true;
            } catch (InvalidArgumentException) {
                // Character not allowed, skip it
            }
        }

        // Special case: check if minus is allowed for negative numbers
        // Try a negative number pattern
        try {
            $type->parseValue('-1');
            $allowedChars[ord('-')] = true;
        } catch (InvalidArgumentException) {
            // Negative numbers not allowed
        }

        return $allowedChars;
    }

    /**
     * Probe length constraints by testing various lengths
     */
    private static function probeLengthConstraints(TypeInterface $type): array
    {
        $minLen = 0;
        $maxLen = null;

        // Find minimum length by testing incrementally
        $minLen = self::findMinimumLength($type);

        // Find maximum length by binary search or constraint detection
        $maxLen = self::findMaximumLength($type, $minLen);

        return [$minLen, $maxLen];
    }

    /**
     * Find the minimum valid length
     */
    private static function findMinimumLength(TypeInterface $type): int
    {
        // Try empty string first
        try {
            $type->parseValue('');
            return 0;
        } catch (InvalidArgumentException) {
            // Empty not allowed
        }

        // Try increasingly longer strings of a safe character
        $testChar = self::findSafeTestCharacter($type);

        for ($len = 1; $len <= 100; $len++) {
            $testValue = str_repeat($testChar, $len);

            try {
                $type->parseValue($testValue);
                // This length works!
                return $len;
            } catch (InvalidArgumentException) {
                // Keep trying longer strings
            }
        }

        // Default to 1 if we can't determine
        return 1;
    }

    /**
     * Find the maximum valid length
     */
    private static function findMaximumLength(TypeInterface $type, int $minLen): ?int
    {
        $testChar = self::findSafeTestCharacter($type);

        // First check if there's any upper limit at all
        // Try a very long string
        $veryLong = str_repeat($testChar, 1000);
        try {
            $type->parseValue($veryLong);
            // No upper limit detected
            return null;
        } catch (InvalidArgumentException) {
            // There is an upper limit, find it
        }

        // Binary search for the maximum length
        $low = $minLen;
        $high = 1000;
        $maxFound = $minLen;

        while ($low <= $high) {
            $mid = (int)(($low + $high) / 2);
            $testValue = str_repeat($testChar, $mid);

            try {
                $type->parseValue($testValue);
                // This length works, try longer
                $maxFound = $mid;
                $low = $mid + 1;
            } catch (InvalidArgumentException) {
                // Too long, try shorter
                $high = $mid - 1;
            }
        }

        return $maxFound;
    }

    /**
     * Find a character that this type accepts for testing
     */
    private static function findSafeTestCharacter(TypeInterface $type): string
    {
        // Common test characters in order of likelihood
        $testChars = ['a', '1', '0', 'A', 'x', '_', '-', '.'];

        foreach ($testChars as $char) {
            try {
                $type->parseValue($char);
                return $char;
            } catch (InvalidArgumentException) {
                // Try next character
            }
        }

        // Fallback: try all printable ASCII
        for ($i = 32; $i < 127; $i++) {
            $char = chr($i);
            try {
                $type->parseValue($char);
                return $char;
            } catch (InvalidArgumentException) {
                // Keep trying
            }
        }

        // Last resort
        return 'a';
    }

    /**
     * Check if empty string is valid
     */
    private static function probeCanBeEmpty(TypeInterface $type): bool
    {
        try {
            $type->parseValue('');
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
