<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer\Type;

use CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer\AnalyzerResult;
use CPSIT\ShortNr\Config\Ast\Types\TypeInterface;
use InvalidArgumentException;

class TypeAnalyzer
{
    // Common character sets for batch testing
    private const CHAR_SETS = [
        'digits' => '0123456789',
        'lower' => 'abcdefghijklmnopqrstuvwxyz',
        'upper' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'special' => '!@#$%^&*()_+-=[]{}|;:,.<>?/\\\'"`~',
        'whitespace' => " \t\n\r",
    ];

    // Cache for analyzed types (since types are immutable)
    private static array $cache = [];

    /**
     * Analyze a type by probing it with test values
     * Uses blackbox testing - no knowledge about specific types needed!
     */
    public static function analyzeType(TypeInterface $type): TypeAnalyzerResult
    {
        // Check cache first (types are immutable)
        $cacheKey = spl_object_id($type);
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // Probe for allowed characters (optimized)
        $allowedChars = self::probeAllowedCharactersOptimized($type);

        // Probe for length constraints
        [$minLen, $maxLen] = self::probeLengthConstraints($type, $allowedChars);

        // Check if empty string is valid
        $canBeEmpty = self::probeCanBeEmpty($type);

        $result = new TypeAnalyzerResult(
            minLen: $minLen,
            maxLen: $maxLen,
            allowedChars: $allowedChars,
            canBeEmpty: $canBeEmpty
        );

        // Cache the result
        self::$cache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Optimized character probing - test character sets first, then individuals
     */
    private static function probeAllowedCharactersOptimized(TypeInterface $type): array
    {
        $allowedChars = [];
        $testedChars = [];

        // First, test common character sets to batch-identify allowed ranges
        foreach (self::CHAR_SETS as $chars) {
            // Try the whole set at once
            try {
                $type->parseValue($chars);
                // If the whole set works, all chars are likely allowed
                for ($i = 0; $i < strlen($chars); $i++) {
                    $char = $chars[$i];
                    $allowedChars[ord($char)] = true;
                    $testedChars[ord($char)] = true;
                }
            } catch (InvalidArgumentException) {
                // Set as a whole failed, test individual chars from this set
                for ($i = 0; $i < strlen($chars); $i++) {
                    $char = $chars[$i];
                    $ord = ord($char);
                    if (!isset($testedChars[$ord])) {
                        try {
                            $type->parseValue($char);
                            $allowedChars[$ord] = true;
                        } catch (InvalidArgumentException) {
                            // Character not allowed
                        }
                        $testedChars[$ord] = true;
                    }
                }
            }
        }

        // Test remaining printable ASCII characters not in sets
        for ($i = 32; $i < 127; $i++) {
            if (!isset($testedChars[$i])) {
                $char = chr($i);
                try {
                    $type->parseValue($char);
                    $allowedChars[$i] = true;
                } catch (InvalidArgumentException) {
                    // Character not allowed
                }
            }
        }

        // Special cases for numeric types
        self::probeNumericPatterns($type, $allowedChars);

        return $allowedChars;
    }

    /**
     * Probe for numeric patterns (negative numbers, decimals, etc.)
     */
    private static function probeNumericPatterns(TypeInterface $type, array &$allowedChars): void
    {
        // Test negative numbers
        $negativeTests = ['-1', '-123', '-999'];
        foreach ($negativeTests as $test) {
            try {
                $type->parseValue($test);
                $allowedChars[ord('-')] = true;
                break; // One success is enough
            } catch (InvalidArgumentException) {
                // Continue testing
            }
        }

        // Test decimal numbers
        $decimalTests = ['1.0', '3.14', '0.5'];
        foreach ($decimalTests as $test) {
            try {
                $type->parseValue($test);
                $allowedChars[ord('.')] = true;
                break; // One success is enough
            } catch (InvalidArgumentException) {
                // Continue testing
            }
        }
    }

    /**
     * Probe length constraints using binary search
     */
    private static function probeLengthConstraints(TypeInterface $type, array $allowedChars): array
    {
        // Find a safe test character from allowed chars
        $testChar = self::findSafeTestCharacter($type, $allowedChars);

        // Find minimum length
        $minLen = self::findMinimumLengthBinary($type, $testChar);

        // Find maximum length
        $maxLen = self::findMaximumLengthBinary($type, $testChar, $minLen);

        return [$minLen, $maxLen];
    }

    /**
     * Find minimum valid length using binary search
     */
    private static function findMinimumLengthBinary(TypeInterface $type, string $testChar): int
    {
        // Quick check for empty string
        try {
            $type->parseValue('');
            return 0;
        } catch (InvalidArgumentException) {
            // Empty not allowed
        }

        // Binary search for minimum length (1-100)
        $low = 1;
        $high = 100;
        $minFound = 100;

        while ($low <= $high) {
            $mid = (int)(($low + $high) / 2);
            $testValue = str_repeat($testChar, $mid);

            try {
                $type->parseValue($testValue);
                // This length works, try shorter
                $minFound = min($minFound, $mid);
                $high = $mid - 1;
            } catch (InvalidArgumentException) {
                // Too short, try longer
                $low = $mid + 1;
            }
        }

        return $minFound;
    }

    /**
     * Find maximum valid length using exponential search then binary search
     */
    private static function findMaximumLengthBinary(TypeInterface $type, string $testChar, int $minLen): ?int
    {
        // First, use exponential search to find upper bound
        $testLen = max(100, $minLen * 10);
        $maxTestLen = AnalyzerResult::MAX_LEN_LIMIT;

        // Find a length that fails
        while ($testLen <= $maxTestLen) {
            $testValue = str_repeat($testChar, $testLen);
            try {
                $type->parseValue($testValue);
                // Still works, double the length
                $testLen *= 2;
            } catch (InvalidArgumentException) {
                // Found upper bound, break
                break;
            }
        }

        // If we hit max test length, and it still works, assume no limit
        if ($testLen > $maxTestLen) {
            $testValue = str_repeat($testChar, $maxTestLen);
            try {
                $type->parseValue($testValue);
                return null; // No upper limit detected
            } catch (InvalidArgumentException) {
                // There is a limit, continue to find it
            }
        }

        // Binary search between minLen and testLen
        $low = $minLen;
        $high = min($testLen, $maxTestLen);
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
     * Prioritize from already known allowed chars
     */
    private static function findSafeTestCharacter(TypeInterface $type, array $allowedChars): string
    {
        // Use first allowed char if we have any
        if (!empty($allowedChars)) {
            $firstAllowed = array_key_first($allowedChars);
            return chr($firstAllowed);
        }

        // Fallback to common test characters
        $testChars = ['a', '1', '0', 'A', 'x', '_', '-', '.'];
        foreach ($testChars as $char) {
            try {
                $type->parseValue($char);
                return $char;
            } catch (InvalidArgumentException) {
                // Try next
            }
        }

        // Last resort - should rarely reach here
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

    /**
     * Clear the type analysis cache (useful for testing or memory management)
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
