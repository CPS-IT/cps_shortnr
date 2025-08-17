<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config\Ast\Heuristic;

use CPSIT\ShortNr\Config\Ast\Nodes\GroupNode;
use CPSIT\ShortNr\Config\Ast\Nodes\LiteralNode;
use CPSIT\ShortNr\Config\Ast\Nodes\NestedAstNode;

/**
 * Ultra-fast pattern pre-filter - Memory-optimized version
 *
 * Philosophy: Memory is cheap, CPU is expensive
 * - Pre-compute everything possible
 * - Trade space for time aggressively
 * - Zero runtime computations
 */
final class PatternHeuristic implements HeuristicPatternInterface
{
    // Pre-computed lookup tables
    private readonly array $literalLookup;      // Hash table for O(1) lookup
    private readonly array $prefixLookup;       // 2-char prefix index
    private readonly array $suffixLookup;       // 2-char suffix index
    private readonly int $minLen;
    private readonly int $maxLen;
    private readonly ?string $charPattern;      // Pre-compiled regex
    private readonly array $charLookup;         // Bitset for valid chars (faster than regex)
    private readonly bool $useCharLookup;       // Whether to use char validation

    // Pre-computed length lookup table for even faster checks
    private readonly array $validLengths;       // Bitset: validLengths[len] = true if valid

    // Constants
    private const MIN_LITERAL_LEN = 2;
    private const DEFAULT_MIN_LEN = 1;
    private const DEFAULT_MAX_LEN = 200;
    private const GROUP_MIN_LEN = 1;
    private const GROUP_MAX_LEN = 20;
    private const PREFIX_LEN = 2;
    private const SUFFIX_LEN = 2;

    private function __construct(
        array $literalLookup,
        array $prefixLookup,
        array $suffixLookup,
        int $minLen,
        int $maxLen,
        array $charLookup,
        bool $useCharLookup,
        array $validLengths,
        ?string $charPattern = null
    ) {
        $this->literalLookup = $literalLookup;
        $this->prefixLookup = $prefixLookup;
        $this->suffixLookup = $suffixLookup;
        $this->minLen = $minLen;
        $this->maxLen = $maxLen;
        $this->charLookup = $charLookup;
        $this->useCharLookup = $useCharLookup;
        $this->validLengths = $validLengths;
        $this->charPattern = $charPattern;
    }

    /**
     * Build heuristic from compiled patterns
     * Heavy pre-computation here saves CPU later
     */
    public static function buildFromPatterns(iterable $compiledPatterns): self
    {
        $allLiterals = [];
        $minLengths = [];
        $maxLengths = [];
        $charSet = [];
        $prefixes = [];
        $suffixes = [];

        foreach ($compiledPatterns as $pattern) {
            $metrics = self::analyzeAst($pattern->getAst());

            // Collect all data
            foreach ($metrics['literals'] as $literal) {
                if (strlen($literal) >= self::MIN_LITERAL_LEN) {
                    $allLiterals[$literal] = true;

                    // Index prefixes
                    if (strlen($literal) >= self::PREFIX_LEN) {
                        $prefix = substr($literal, 0, self::PREFIX_LEN);
                        $prefixes[$prefix] = true;
                    }

                    // Index suffixes
                    if (strlen($literal) >= self::SUFFIX_LEN) {
                        $suffix = substr($literal, -self::SUFFIX_LEN);
                        $suffixes[$suffix] = true;
                    }
                }

                // Build character set
                foreach (str_split($literal) as $char) {
                    $charSet[ord($char)] = true;
                }
            }

            $minLengths[] = $metrics['minLen'];
            $maxLengths[] = $metrics['maxLen'];
        }

        $minLen = empty($minLengths) ? self::DEFAULT_MIN_LEN : min($minLengths);
        $maxLen = empty($maxLengths) ? self::DEFAULT_MAX_LEN : max($maxLengths);

        // Pre-compute valid lengths bitset (massive speedup for range checks)
        $validLengths = [];
        for ($i = $minLen; $i <= $maxLen; $i++) {
            $validLengths[$i] = true;
        }

        // Build character lookup table (ASCII bitset - faster than regex)
        $charLookup = [];
        $useCharLookup = !empty($charSet);
        if ($useCharLookup) {
            // Pre-compute for all ASCII chars
            for ($i = 0; $i < 256; $i++) {
                $charLookup[$i] = isset($charSet[$i]);
            }
        }

        // Also build regex pattern as fallback for non-ASCII
        $charPattern = null;
        if (!empty($charSet)) {
            $escaped = [];
            foreach (array_keys($charSet) as $ord) {
                $char = chr($ord);
                if (in_array($char, ['-', ']', '\\', '^'], true)) {
                    $escaped[] = '\\' . $char;
                } else {
                    $escaped[] = $char;
                }
            }
            $charPattern = '/^[' . implode('', $escaped) . ']+$/';
        }

        return new self(
            $allLiterals,
            $prefixes,
            $suffixes,
            $minLen,
            $maxLen,
            $charLookup,
            $useCharLookup,
            $validLengths,
            $charPattern
        );
    }

    /**
     * Analyze AST node recursively
     */
    private static function analyzeAst($node, bool $optional = false): array
    {
        $literals = [];
        $minLen = 0;
        $maxLen = 0;

        if ($node instanceof LiteralNode) {
            $text = $node->getText();
            $literals[] = $text;
            $len = strlen($text);
            $minLen = $optional ? 0 : $len;
            $maxLen = $len;

        } elseif ($node instanceof GroupNode) {
            $isOptional = $optional || $node->isOptional();
            $minLen = $isOptional ? 0 : self::GROUP_MIN_LEN;
            $maxLen = self::GROUP_MAX_LEN;

        } elseif ($node instanceof NestedAstNode) {
            $childOptional = $optional || ($node instanceof \CPSIT\ShortNr\Config\Ast\Nodes\SubSequenceNode);
            foreach ($node->getChildren() as $child) {
                $childMetrics = self::analyzeAst($child, $childOptional);
                $literals = array_merge($literals, $childMetrics['literals']);
                $minLen += $childMetrics['minLen'];
                $maxLen += $childMetrics['maxLen'];
            }
        }

        return [
            'literals' => $literals,
            'minLen' => $minLen,
            'maxLen' => $maxLen
        ];
    }

    /**
     * HOT PATH - Maximum performance, zero allocations
     * Every nanosecond counts here
     */
    public function support(string $string): bool
    {
        // Reject obviously too long strings (performance optimization)
        if (strlen($string) > $this->maxLen) {
            return false;
        }
        
        // Simple heuristic: check if input starts with any known literal
        foreach ($this->literalLookup as $literal => $_) {
            if (str_starts_with($string, $literal)) {
                return true;
            }
        }
        
        // If no literals, accept everything (no heuristic possible)
        return empty($this->literalLookup);
    }

    /**
     * Hydrate from cached data
     */
    public static function fromArray(array $data): self
    {
        // Rebuild valid lengths bitset
        $validLengths = [];
        $minLen = $data['minLen'] ?? self::DEFAULT_MIN_LEN;
        $maxLen = $data['maxLen'] ?? self::DEFAULT_MAX_LEN;
        for ($i = $minLen; $i <= $maxLen; $i++) {
            $validLengths[$i] = true;
        }

        return new self(
            $data['literals'] ?? [],
            $data['prefixes'] ?? [],
            $data['suffixes'] ?? [],
            $minLen,
            $maxLen,
            $data['charLookup'] ?? [],
            $data['useCharLookup'] ?? false,
            $validLengths,
            $data['charPattern'] ?? null
        );
    }

    /**
     * Export for caching (includes all pre-computed data)
     */
    public function toArray(): array
    {
        return [
            'version' => '3.0',
            'literals' => $this->literalLookup,
            'prefixes' => $this->prefixLookup,
            'suffixes' => $this->suffixLookup,
            'minLen' => $this->minLen,
            'maxLen' => $this->maxLen,
            'charLookup' => $this->charLookup,
            'useCharLookup' => $this->useCharLookup,
            'charPattern' => $this->charPattern
        ];
    }
}
