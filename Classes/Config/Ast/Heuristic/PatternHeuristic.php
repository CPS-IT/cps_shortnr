<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic;

use CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer\NodeAnalyzer;

/**
 * Ultra-fast pattern pre-filter - Conservative rejection strategy
 *
 * CRITICAL RULE: Never produce false negatives!
 * - Only reject if 100% certain the input cannot match
 * - When in doubt, return true (let the real matcher decide)
 * - False positives are acceptable (performance trade-off)
 * - False negatives are NEVER acceptable (correctness requirement)
 *
 * Fast rejection strategies (all must be 100% certain):
 * 1. Length bounds - reject if too short or too long
 * 2. Required literals - reject if missing literals that appear in ALL paths
 * 3. Character set - reject if contains impossible characters
 */
final class PatternHeuristic implements HeuristicPatternInterface
{
    // Length bounds (certain rejection if outside range)
    private readonly int $minLen;
    private readonly int $maxLen;

    // Literal-based heuristics
    private readonly array $requiredLiterals;      // Literals that MUST appear somewhere
    private readonly ?string $requiredPrefix;      // Prefix required by ALL patterns
    private readonly ?string $requiredSuffix;      // Suffix required by ALL patterns

    // Literal map for quick pattern filtering
    private readonly array $startsWithMap;         // prefix => true (any pattern starts with this)
    private readonly array $endsWithMap;           // suffix => true (any pattern ends with this)
    private readonly array $containsMap;           // literal => true (any pattern contains this)
    private readonly bool $hasLiteralMaps;         // Whether we have any literal maps

    // Character validation (only for patterns with restricted char sets)
    private readonly array $validAscii;            // ASCII bitset [0-255] => bool
    private readonly bool $hasCharRestrictions;    // Whether to check characters

    // Quick flags for hot path optimization
    private readonly bool $hasRequirements;        // Any requirements at all?
    private readonly bool $alwaysAccept;           // No reliable heuristics available

    private function __construct(
        int $minLen,
        int $maxLen,
        array $requiredLiterals,
        ?string $requiredPrefix,
        ?string $requiredSuffix,
        array $startsWithMap,
        array $endsWithMap,
        array $containsMap,
        bool $hasLiteralMaps,
        array $validAscii,
        bool $hasCharRestrictions,
        bool $hasRequirements,
        bool $alwaysAccept
    ) {
        $this->minLen = $minLen;
        $this->maxLen = $maxLen;
        $this->requiredLiterals = $requiredLiterals;
        $this->requiredPrefix = $requiredPrefix;
        $this->requiredSuffix = $requiredSuffix;
        $this->startsWithMap = $startsWithMap;
        $this->endsWithMap = $endsWithMap;
        $this->containsMap = $containsMap;
        $this->hasLiteralMaps = $hasLiteralMaps;
        $this->validAscii = $validAscii;
        $this->hasCharRestrictions = $hasCharRestrictions;
        $this->hasRequirements = $hasRequirements;
        $this->alwaysAccept = $alwaysAccept;
    }

    /**
     * Build heuristic from compiled patterns
     * Extracts ONLY certain requirements (no false negatives!)
     */
    public static function buildFromPatterns(iterable $compiledPatterns): self
    {
        $Analyzes = [];
        foreach ($compiledPatterns as $pattern) {
            $Analyzes[] = NodeAnalyzer::analyzeNode($pattern->getAst());
        }

        if (empty($Analyzes)) {
            return self::createAlwaysAccept();
        }

        // Find COMMON requirements across ALL patterns

        // 1. Length bounds (min of mins, max of maxes)
        $minLen = PHP_INT_MAX;
        $maxLen = 0;
        foreach ($Analyzes as $analysis) {
            $minLen = min($minLen, $analysis['minLen']);
            $maxLen = max($maxLen, $analysis['maxLen']);
        }

        // 2. Common required prefix (must be same in ALL patterns)
        $requiredPrefix = null;
        $firstPrefix = $Analyzes[0]['prefix'] ?? null;
        if ($firstPrefix !== null) {
            $allHaveSamePrefix = true;
            foreach ($Analyzes as $analysis) {
                if (($analysis['prefix'] ?? null) !== $firstPrefix) {
                    $allHaveSamePrefix = false;
                    break;
                }
            }
            if ($allHaveSamePrefix) {
                $requiredPrefix = $firstPrefix;
            }
        }

        // 3. Common required suffix (must be same in ALL patterns)
        $requiredSuffix = null;
        $firstSuffix = $Analyzes[0]['suffix'] ?? null;
        if ($firstSuffix !== null) {
            $allHaveSameSuffix = true;
            foreach ($Analyzes as $analysis) {
                if (($analysis['suffix'] ?? null) !== $firstSuffix) {
                    $allHaveSameSuffix = false;
                    break;
                }
            }
            if ($allHaveSameSuffix) {
                $requiredSuffix = $firstSuffix;
            }
        }

        // 4. Required literals (must appear in ALL patterns)
        $requiredLiterals = [];
        $firstLiterals = $Analyzes[0]['requiredLiterals'];
        foreach ($firstLiterals as $literal => $_) {
            $inAllPatterns = true;
            foreach ($Analyzes as $analysis) {
                if (!isset($analysis['requiredLiterals'][$literal])) {
                    $inAllPatterns = false;
                    break;
                }
            }
            if ($inAllPatterns) {
                $requiredLiterals[$literal] = true;
            }
        }

        // 5. Build literal maps (for positive matching - ANY pattern has this)
        $startsWithMap = [];
        $endsWithMap = [];
        $containsMap = [];

        foreach ($Analyzes as $analysis) {
            // Collect starting literals
            if ($analysis['prefix'] !== null) {
                $startsWithMap[$analysis['prefix']] = true;
            }

            // Collect ending literals
            if ($analysis['suffix'] !== null) {
                $endsWithMap[$analysis['suffix']] = true;
            }

            // Collect all contained literals
            foreach ($analysis['allLiterals'] ?? [] as $literal => $_) {
                $containsMap[$literal] = true;
            }
        }

        $hasLiteralMaps = !empty($startsWithMap) || !empty($endsWithMap) || !empty($containsMap);

        // 6. Character set (union of all allowed chars - can only reject if char is in NONE)
        $validAscii = [];
        $hasCharRestrictions = false;

        // Collect all allowed characters from all patterns
        foreach ($Analyzes as $analysis) {
            foreach ($analysis['allowedChars'] as $ord => $_) {
                $validAscii[$ord] = true;
            }
        }

        // Only enable char validation if we have meaningful restrictions
        // (i.e., not all ASCII chars are allowed)
        if (!empty($validAscii) && count($validAscii) < 128) {
            $hasCharRestrictions = true;
            // Convert to bitset for O(1) lookup
            $ascii = [];
            for ($i = 0; $i < 256; $i++) {
                $ascii[$i] = isset($validAscii[$i]);
            }
            $validAscii = $ascii;
        } else {
            $validAscii = [];
        }

        // Determine if we have any requirements
        $hasRequirements = ($minLen > 0) ||
            ($maxLen < PHP_INT_MAX) ||
            ($requiredPrefix !== null) ||
            ($requiredSuffix !== null) ||
            (!empty($requiredLiterals)) ||
            $hasLiteralMaps ||
            $hasCharRestrictions;

        // Special case: if we have NO literals at all in any pattern, we can't filter much
        $patternsWithoutLiterals = true;
        foreach ($Analyzes as $analysis) {
            if (!empty($analysis['allLiterals']) || $analysis['prefix'] !== null || $analysis['suffix'] !== null) {
                $patternsWithoutLiterals = false;
                break;
            }
        }

        // If no patterns have literals, we can only use length/char restrictions
        $alwaysAccept = !$hasRequirements || $patternsWithoutLiterals;

        return new self(
            $minLen === PHP_INT_MAX ? 0 : $minLen,
            $maxLen === 0 ? PHP_INT_MAX : $maxLen,
            $requiredLiterals,
            $requiredPrefix,
            $requiredSuffix,
            $startsWithMap,
            $endsWithMap,
            $containsMap,
            $hasLiteralMaps,
            $validAscii,
            $hasCharRestrictions,
            $hasRequirements,
            $alwaysAccept
        );
    }

    /**
     * Create an always-accept heuristic (no patterns to check)
     */
    private static function createAlwaysAccept(): self
    {
        return new self(
            0,
            PHP_INT_MAX,
            [],
            null,
            null,
            [],
            [],
            [],
            false,
            [],
            true,
            false,
            false
        );
    }

    /**
     * HOT PATH - Maximum performance, zero allocations
     * Returns true if input MIGHT match (conservative)
     * Only returns false if input CANNOT match (certain)
     */
    public function support(string $string): bool
    {
        // Fast path: no requirements means always accept
        if ($this->alwaysAccept) {
            return true;
        }

        $len = strlen($string);

        // 1. Length check - certain rejection if outside bounds
        if ($len < $this->minLen || $len > $this->maxLen) {
            return false;
        }

        // 2. Required prefix - certain rejection if missing
        if ($this->requiredPrefix !== null) {
            if (!str_starts_with($string, $this->requiredPrefix)) {
                return false;
            }
        }

        // 3. Required suffix - certain rejection if missing
        if ($this->requiredSuffix !== null) {
            if (!str_ends_with($string, $this->requiredSuffix)) {
                return false;
            }
        }

        // 4. Required literals - certain rejection if any missing
        if (!empty($this->requiredLiterals)) {
            foreach ($this->requiredLiterals as $literal => $_) {
                if (!str_contains($string, $literal)) {
                    return false;
                }
            }
        }

        // 5. Character validation - certain rejection if invalid chars
        if ($this->hasCharRestrictions) {
            for ($i = 0; $i < $len; $i++) {
                $ord = ord($string[$i]);
                if (!$this->validAscii[$ord]) {
                    return false;  // Contains a character that NO pattern allows
                }
            }
        }

        // Cannot reject with certainty - might match
        return true;
    }

    /**
     * Hydrate from cached data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['minLen'] ?? 0,
            $data['maxLen'] ?? PHP_INT_MAX,
            $data['requiredLiterals'] ?? [],
            $data['requiredPrefix'] ?? null,
            $data['requiredSuffix'] ?? null,
            $data['startsWithMap'] ?? [],
                $data['endsWithMap'] ?? [],
                $data['containsMap'] ?? [],
            $data['hasLiteralMaps'] ?? false,
            $data['validAscii'] ?? [],
            $data['hasCharRestrictions'] ?? false,
            $data['hasRequirements'] ?? false,
            $data['alwaysAccept'] ?? true
        );
    }

    /**
     * Export for caching
     */
    public function toArray(): array
    {
        return [
            'version' => '4.0',
            'minLen' => $this->minLen,
            'maxLen' => $this->maxLen,
            'requiredLiterals' => $this->requiredLiterals,
            'requiredPrefix' => $this->requiredPrefix,
            'requiredSuffix' => $this->requiredSuffix,
            'startsWithMap' => $this->startsWithMap,
            'endsWithMap' => $this->endsWithMap,
            'containsMap' => $this->containsMap,
            'hasLiteralMaps' => $this->hasLiteralMaps,
            'validAscii' => $this->validAscii,
            'hasCharRestrictions' => $this->hasCharRestrictions,
            'hasRequirements' => $this->hasRequirements,
            'alwaysAccept' => $this->alwaysAccept
        ];
    }
}
