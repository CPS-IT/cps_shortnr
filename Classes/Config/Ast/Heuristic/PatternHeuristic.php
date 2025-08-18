<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic;

use CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer\NodeAnalyzer;
use CPSIT\ShortNr\Config\Ast\Heuristic\Analyzer\AnalyzerResult;
use CPSIT\ShortNr\Exception\ShortNrPatternException;

/**
 * Ultra-fast pattern pre-filter - Conservative rejection strategy
 *
 * CRITICAL RULE: Never produce false negatives!
 * - Only reject if 100% certain the input cannot match
 * - When in doubt, return true (let the real matcher decide)
 * - False positives are acceptable (performance trade-off)
 * - False negatives are NEVER acceptable (correctness requirement)
 */
final class PatternHeuristic implements HeuristicPatternInterface
{
    // Quick rejection checks (all must be 100% certain)
    private readonly int $minLen;               // Minimum possible length across all patterns
    private readonly int $maxLen;               // Maximum possible length across all patterns
    private readonly array $requiredLiterals;   // Literals required by ALL patterns
    private readonly ?string $commonPrefix;     // Common prefix in ALL patterns
    private readonly ?string $commonSuffix;     // Common suffix in ALL patterns
    private readonly array $possiblePrefixes;   // Set of prefixes from ANY pattern (for rejection)
    private readonly array $possibleSuffixes;   // Set of suffixes from ANY pattern (for rejection)
    private readonly array $allowedChars;       // Union of all allowed chars (bitset)
    private readonly bool $hasChecks;           // Whether we have any checks to perform

    private function __construct(
        int $minLen,
        int $maxLen,
        array $requiredLiterals,
        ?string $commonPrefix,
        ?string $commonSuffix,
        array $possiblePrefixes,
        array $possibleSuffixes,
        array $allowedChars,
        bool $hasChecks
    ) {
        $this->minLen = $minLen;
        $this->maxLen = $maxLen;
        $this->requiredLiterals = $requiredLiterals;
        $this->commonPrefix = $commonPrefix;
        $this->commonSuffix = $commonSuffix;
        $this->possiblePrefixes = $possiblePrefixes;
        $this->possibleSuffixes = $possibleSuffixes;
        $this->allowedChars = $allowedChars;
        $this->hasChecks = $hasChecks;
    }

    /**
     * Build heuristic from compiled patterns
     * Extracts ONLY certain requirements (no false negatives!)
     *
     * @throws ShortNrPatternException
     */
    public static function buildFromPatterns(iterable $compiledPatterns): self
    {
        /** @var AnalyzerResult[] $analyses */
        $analyses = [];

        foreach ($compiledPatterns as $pattern) {
            $analyses[] = NodeAnalyzer::analyzeNode($pattern->getAst());
        }

        if (empty($analyses)) {
            return self::createAlwaysAccept();
        }

        // Extract heuristic properties from analyses
        $heuristics = self::extractHeuristics($analyses);

        return new self(
            minLen: $heuristics['minLen'],
            maxLen: $heuristics['maxLen'],
            requiredLiterals: $heuristics['requiredLiterals'],
            commonPrefix: $heuristics['commonPrefix'],
            commonSuffix: $heuristics['commonSuffix'],
            possiblePrefixes: $heuristics['possiblePrefixes'],
            possibleSuffixes: $heuristics['possibleSuffixes'],
            allowedChars: $heuristics['allowedChars'],
            hasChecks: $heuristics['hasChecks']
        );
    }

    /**
     * Extract heuristic properties from analysis results
     */
    private static function extractHeuristics(array $analyses): array
    {
        // 1. Length bounds (min of mins, max of maxes)
        $minLen = PHP_INT_MAX;
        $maxLen = 0;

        foreach ($analyses as $analysis) {
            $minLen = min($minLen, $analysis->getMinLen());
            $analysisMax = $analysis->getMaxLen();

            // Handle unlimited max length
            if ($analysisMax === null) {
                $maxLen = PHP_INT_MAX;
            } elseif ($maxLen !== PHP_INT_MAX) {
                $maxLen = max($maxLen, $analysisMax);
            }
        }

        // Normalize min/max
        if ($minLen === PHP_INT_MAX) {
            $minLen = 0;
        }
        if ($maxLen === 0) {
            $maxLen = PHP_INT_MAX;
        }

        // 2. Find common prefix (must be same in ALL patterns)
        $commonPrefix = self::findCommonPrefix($analyses);

        // 3. Find common suffix (must be same in ALL patterns)
        $commonSuffix = self::findCommonSuffix($analyses);

        // 4. Collect ALL possible prefixes (for rejection of invalid prefixes)
        $possiblePrefixes = self::collectPossiblePrefixes($analyses);

        // 5. Collect ALL possible suffixes (for rejection of invalid suffixes)
        $possibleSuffixes = self::collectPossibleSuffixes($analyses);

        // 6. Find required literals (must appear in ALL patterns as required)
        $requiredLiterals = self::findRequiredLiterals($analyses);

        // 7. Build allowed character set (union of all - for rejection of impossible chars)
        $allowedChars = self::buildAllowedCharSet($analyses);

        // 8. Determine if we have any meaningful checks
        $hasChecks = ($minLen > 0) ||
            ($maxLen < PHP_INT_MAX) ||
            ($commonPrefix !== null) ||
            ($commonSuffix !== null) ||
            (!empty($possiblePrefixes)) ||
            (!empty($possibleSuffixes)) ||
            (!empty($requiredLiterals)) ||
            (!empty($allowedChars) && count(array_filter($allowedChars)) < 256);

        return [
            'minLen' => $minLen,
            'maxLen' => $maxLen,
            'requiredLiterals' => $requiredLiterals,
            'commonPrefix' => $commonPrefix,
            'commonSuffix' => $commonSuffix,
            'possiblePrefixes' => $possiblePrefixes,
            'possibleSuffixes' => $possibleSuffixes,
            'allowedChars' => $allowedChars,
            'hasChecks' => $hasChecks
        ];
    }

    /**
     * Find common prefix across all patterns
     */
    private static function findCommonPrefix(array $analyses): ?string
    {
        if (empty($analyses)) {
            return null;
        }

        $firstPrefix = $analyses[0]->getPrefix();
        if ($firstPrefix === null) {
            return null;
        }

        // Check if all patterns have the same prefix
        foreach ($analyses as $analysis) {
            if ($analysis->getPrefix() !== $firstPrefix) {
                return null;
            }
        }

        return $firstPrefix;
    }

    /**
     * Find common suffix across all patterns
     */
    private static function findCommonSuffix(array $analyses): ?string
    {
        if (empty($analyses)) {
            return null;
        }

        $firstSuffix = $analyses[0]->getSuffix();
        if ($firstSuffix === null) {
            return null;
        }

        // Check if all patterns have the same suffix
        foreach ($analyses as $analysis) {
            if ($analysis->getSuffix() !== $firstSuffix) {
                return null;
            }
        }

        return $firstSuffix;
    }

    /**
     * Collect all possible prefixes from any pattern
     */
    private static function collectPossiblePrefixes(array $analyses): array
    {
        $prefixes = [];

        foreach ($analyses as $analysis) {
            $prefix = $analysis->getPrefix();
            if ($prefix !== null && $prefix !== '') {
                $prefixes[$prefix] = true;
            }
        }

        return $prefixes;
    }

    /**
     * Collect all possible suffixes from any pattern
     */
    private static function collectPossibleSuffixes(array $analyses): array
    {
        $suffixes = [];

        foreach ($analyses as $analysis) {
            $suffix = $analysis->getSuffix();
            if ($suffix !== null && $suffix !== '') {
                $suffixes[$suffix] = true;
            }
        }

        return $suffixes;
    }

    /**
     * Find literals that are required in ALL patterns
     */
    private static function findRequiredLiterals(array $analyses): array
    {
        if (empty($analyses)) {
            return [];
        }

        // Start with literals from first pattern
        $requiredLiterals = [];
        $firstLiterals = $analyses[0]->getLiterals();

        // Only consider literals that are required in the first pattern
        foreach ($firstLiterals as $literal => $isRequired) {
            if (!$isRequired) {
                continue;
            }

            // Check if this literal is required in ALL other patterns
            $requiredInAll = true;
            for ($i = 1; $i < count($analyses); $i++) {
                $otherLiterals = $analyses[$i]->getLiterals();
                if (!isset($otherLiterals[$literal]) || !$otherLiterals[$literal]) {
                    $requiredInAll = false;
                    break;
                }
            }

            if ($requiredInAll) {
                $requiredLiterals[$literal] = true;
            }
        }

        return $requiredLiterals;
    }

    /**
     * Build allowed character set (union of all patterns)
     */
    private static function buildAllowedCharSet(array $analyses): array
    {
        $allowedChars = [];

        // Collect all allowed characters from all patterns
        foreach ($analyses as $analysis) {
            $allowedChars += $analysis->getAllowedChars();
        }

        // Convert to efficient bitset if we have restrictions
        if (!empty($allowedChars) && count($allowedChars) < 256) {
            $bitset = array_fill(0, 256, false);
            foreach ($allowedChars as $ord => $_) {
                $bitset[$ord] = true;
            }
            return $bitset;
        }

        return [];
    }

    /**
     * Create an always-accept heuristic (no patterns or no meaningful checks)
     */
    private static function createAlwaysAccept(): self
    {
        return new self(
            minLen: 0,
            maxLen: PHP_INT_MAX,
            requiredLiterals: [],
            commonPrefix: null,
            commonSuffix: null,
            possiblePrefixes: [],
            possibleSuffixes: [],
            allowedChars: [],
            hasChecks: false
        );
    }

    /**
     * HOT PATH - Maximum performance, zero allocations
     * Returns true if input MIGHT match (conservative)
     * Only returns false if input CANNOT match (certain)
     */
    public function support(string $string): bool
    {
        // Fast path: no meaningful checks means always accept
        if (!$this->hasChecks) {
            return true;
        }

        $len = strlen($string);

        // 1. Length check - certain rejection if outside bounds
        if ($len < $this->minLen || $len > $this->maxLen) {
            return false;
        }

        // 2. Common prefix - certain rejection if missing (ALL patterns have this)
        if ($this->commonPrefix !== null) {
            if (!str_starts_with($string, $this->commonPrefix)) {
                return false;
            }
        }

        // 3. Possible prefixes - reject if it doesn't match ANY known prefix
        if (!empty($this->possiblePrefixes)) {
            $matchesAnyPrefix = false;
            foreach ($this->possiblePrefixes as $prefix => $_) {
                if (str_starts_with($string, $prefix)) {
                    $matchesAnyPrefix = true;
                    break;
                }
            }
            if (!$matchesAnyPrefix) {
                return false; // Doesn't match ANY pattern's prefix
            }
        }

        // 4. Common suffix - certain rejection if missing (ALL patterns have this)
        if ($this->commonSuffix !== null) {
            if (!str_ends_with($string, $this->commonSuffix)) {
                return false;
            }
        }

        // 5. Possible suffixes - reject if it doesn't match ANY known suffix
        if (!empty($this->possibleSuffixes)) {
            $matchesAnySuffix = false;
            foreach ($this->possibleSuffixes as $suffix => $_) {
                if (str_ends_with($string, $suffix)) {
                    $matchesAnySuffix = true;
                    break;
                }
            }
            if (!$matchesAnySuffix) {
                return false; // Doesn't match ANY pattern's suffix
            }
        }

        // 6. Required literals - certain rejection if any missing
        foreach ($this->requiredLiterals as $literal => $_) {
            if (!str_contains($string, $literal)) {
                return false;
            }
        }

        // 7. Character validation - certain rejection if contains impossible chars
        if (!empty($this->allowedChars)) {
            for ($i = 0; $i < $len; $i++) {
                if (!$this->allowedChars[ord($string[$i])]) {
                    return false;  // Contains character that NO pattern allows
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
        // Handle version compatibility
        $version = $data['version'] ?? '1.0';

        return new self(
            minLen: $data['minLen'] ?? 0,
            maxLen: $data['maxLen'] ?? PHP_INT_MAX,
            requiredLiterals: $data['requiredLiterals'] ?? [],
            commonPrefix: $data['commonPrefix'] ?? $data['requiredPrefix'] ?? null,
            commonSuffix: $data['commonSuffix'] ?? $data['requiredSuffix'] ?? null,
            possiblePrefixes: $data['possiblePrefixes'] ?? [],
            possibleSuffixes: $data['possibleSuffixes'] ?? [],
            allowedChars: $data['allowedChars'] ?? $data['validAscii'] ?? [],
            hasChecks: $data['hasChecks'] ?? $data['hasRequirements'] ?? true
        );
    }

    /**
     * Export for caching
     */
    public function toArray(): array
    {
        return [
            'version' => '5.0',
            'minLen' => $this->minLen,
            'maxLen' => $this->maxLen === PHP_INT_MAX ? null : $this->maxLen,
            'requiredLiterals' => $this->requiredLiterals,
            'commonPrefix' => $this->commonPrefix,
            'commonSuffix' => $this->commonSuffix,
            'possiblePrefixes' => $this->possiblePrefixes,
            'possibleSuffixes' => $this->possibleSuffixes,
            'allowedChars' => $this->allowedChars,
            'hasChecks' => $this->hasChecks
        ];
    }

    /**
     * Get statistics about the heuristic (for debugging/monitoring)
     */
    public function getStats(): array
    {
        return [
            'minLen' => $this->minLen,
            'maxLen' => $this->maxLen === PHP_INT_MAX ? 'unlimited' : $this->maxLen,
            'requiredLiteralsCount' => count($this->requiredLiterals),
            'hasCommonPrefix' => $this->commonPrefix !== null,
            'hasCommonSuffix' => $this->commonSuffix !== null,
            'possiblePrefixesCount' => count($this->possiblePrefixes),
            'possibleSuffixesCount' => count($this->possibleSuffixes),
            'allowedCharsCount' => empty($this->allowedChars) ? 'all' : count(array_filter($this->allowedChars)),
            'hasChecks' => $this->hasChecks
        ];
    }
}
