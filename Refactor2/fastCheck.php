<?php
declare(strict_types=1);

/**
 * TODO: USE THE SAME AST as the PoC decoder / encoder
 * Heuristic Pre-Matcher for Pattern Compiler
 * Quickly determines if a URL could potentially match ANY pattern
 */

class PatternHeuristic
{
    private string $prefixTree = '';
    private array $commonPrefixes = [];
    private array $requiredSegments = [];
    private string $characterClass = '';
    private ?int $minLength = null;
    private ?int $maxLength = null;
    private ?string $quickRejectRegex = null;
    private ?string $quickAcceptRegex = null;

    /**
     * Build heuristic data from multiple patterns
     */
    public static function buildFromPatterns(array $patterns): self
    {
        $heuristic = new self();
        $heuristic->analyze($patterns);
        return $heuristic;
    }

    /**
     * Analyze patterns to build heuristic rules
     */
    private function analyze(array $patterns): void
    {
        $prefixes = [];
        $lengths = [];
        $chars = [];
        $staticParts = [];

        foreach ($patterns as $pattern) {
            // Extract static prefix (everything before first {)
            if (preg_match('/^([^{(]+)/', $pattern, $m)) {
                $prefix = $m[1];
                $prefixes[] = $prefix;

                // Track first segment for quick filtering
                if (preg_match('/^([A-Za-z0-9_-]+)/', $prefix, $m2)) {
                    $staticParts[] = $m2[1];
                }
            } else {
                // Pattern starts with dynamic content
                $prefixes[] = '';
            }

            // Estimate min/max length
            $minLen = $this->estimateMinLength($pattern);
            $maxLen = $this->estimateMaxLength($pattern);
            $lengths[] = ['min' => $minLen, 'max' => $maxLen];

            // Collect character classes used
            $chars = array_merge($chars, $this->extractCharacterClasses($pattern));
        }

        // Build prefix tree for fast prefix matching
        $this->buildPrefixData($prefixes);

        // Set length constraints
        $this->minLength = min(array_column($lengths, 'min'));
        $this->maxLength = max(array_column($lengths, 'max'));

        // Build character class for quick rejection
        $this->buildCharacterClass($chars);

        // Build quick reject/accept regexes
        $this->buildQuickRegexes($patterns, $staticParts);
    }

    /**
     * Build prefix matching data
     */
    private function buildPrefixData(array $prefixes): void
    {
        // Find common prefixes
        $this->commonPrefixes = array_unique(array_filter($prefixes));

        // Build a simple prefix tree (just first few chars for speed)
        $prefixStarts = [];
        foreach ($this->commonPrefixes as $prefix) {
            if (strlen($prefix) >= 2) {
                $prefixStarts[] = substr($prefix, 0, 2);
            }
        }
        $this->prefixTree = implode('|', array_unique($prefixStarts));
    }

    /**
     * Estimate minimum length a pattern could match
     */
    private function estimateMinLength(string $pattern): int
    {
        // Remove all optional parts
        $pattern = preg_replace('/\([^)]*\)\?/', '', $pattern);
        $pattern = preg_replace('/\{[^}]+\}\?/', '', $pattern);

        // Count minimum literals
        $literals = preg_replace('/\{[^}]+\}/', '', $pattern);
        $minLiteralLen = strlen($literals);

        // Count required groups (assume min 1 char each)
        preg_match_all('/\{[^}]+\}/', $pattern, $groups);
        $minGroupLen = count($groups[0]);

        return $minLiteralLen + $minGroupLen;
    }

    /**
     * Estimate maximum length a pattern could match
     */
    private function estimateMaxLength(string $pattern): int
    {
        // Rough estimate - if pattern has constraints, it's bounded
        if (str_contains($pattern, 'max=')) {
            return 200; // Reasonable upper bound
        }

        // For patterns without max constraints
        return 500; // Safety limit
    }

    /**
     * Extract character classes from pattern
     */
    private function extractCharacterClasses(string $pattern): array
    {
        $classes = [];

        // Check for types used
        if (str_contains($pattern, ':int')) {
            $classes[] = '0-9';
        }
        if (str_contains($pattern, ':alpha')) {
            $classes[] = 'a-zA-Z';
        }
        if (str_contains($pattern, ':alnum')) {
            $classes[] = 'a-zA-Z0-9';
        }
        if (str_contains($pattern, ':slug')) {
            $classes[] = 'a-z0-9\-';
        }

        // Add literal characters
        $literals = preg_replace('/\{[^}]+\}/', '', $pattern);
        $literals = preg_replace('/\([^)]*\)\?/', '', $literals);
        if (preg_match_all('/[a-zA-Z0-9_\-\/.]/', $literals, $m)) {
            // Properly escape special regex characters
            $uniqueChars = array_unique($m[0]);
            foreach ($uniqueChars as $char) {
                if (in_array($char, ['-', '/', '.', '\\'])) {
                    $classes[] = '\\' . $char;
                } else {
                    $classes[] = $char;
                }
            }
        }

        return $classes;
    }

    /**
     * Build character class regex for validation
     */
    private function buildCharacterClass(array $chars): void
    {
        $allChars = array_unique($chars);
        if (empty($allChars)) {
            $this->characterClass = '.';
        } else {
            $this->characterClass = '[' . implode('', $allChars) . ']+';
        }
    }

    /**
     * Build quick regex patterns for fast accept/reject
     */
    private function buildQuickRegexes(array $patterns, array $staticParts): void
    {
        // Quick reject: if URL contains characters never used in any pattern
        // This is inverse of character class
        $this->quickRejectRegex = '/[^' . str_replace('[', '', str_replace(']', '', $this->characterClass)) . ']/';

        // Quick accept: if URL starts with any known static prefix
        if (!empty($staticParts)) {
            // Properly escape for regex with delimiter
            $escapedParts = array_map(function($part) {
                return preg_quote($part, '/');
            }, array_unique($staticParts));
            $this->quickAcceptRegex = '/^(' . implode('|', $escapedParts) . ')/';
        }
    }

    /**
     * Quick check if input could potentially match any pattern
     * Returns: probability score 0-100
     */
    public function couldMatch(string $input): int
    {
        $score = 30; // Start lower (was 50)

        // Length check (fast)
        $len = strlen($input);
        if ($this->minLength !== null && $len < $this->minLength) {
            return 0; // Too short
        }
        if ($this->maxLength !== null && $len > $this->maxLength) {
            return 0; // Too long
        }

        // Character class check (fast)
        if ($this->quickRejectRegex && preg_match($this->quickRejectRegex, $input)) {
            return 0; // Contains invalid characters
        }

        // Known prefix check (fast) - HIGHEST PRIORITY
        foreach ($this->commonPrefixes as $prefix) {
            if (str_starts_with($input, $prefix)) {
                return 95; // Almost certainly a match
            }
        }

        // Quick accept regex (known static parts)
        if ($this->quickAcceptRegex && preg_match($this->quickAcceptRegex, $input)) {
            return 90; // Very likely a short URL
        }

        // Check if it looks like a typical URL path (but not too generic)
        if (preg_match('/^[a-zA-Z0-9]{2,}\/[a-zA-Z0-9]/', $input)) {
            $score += 30; // Looks like a path pattern
        } elseif (preg_match('/^[a-zA-Z]{2,6}[0-9]/', $input)) {
            $score += 40; // Looks like PREFIX+number pattern
        }

        // Penalize generic strings without structure
        if (!str_contains($input, '/') && !preg_match('/^[A-Z]{2,}[0-9]/', $input)) {
            $score -= 20; // Probably not a short URL
        }

        // Strong penalty for typical file extensions
        if (preg_match('/\.(html|php|css|js|jpg|png|gif|pdf|xml|json)$/i', $input)) {
            return 0; // Definitely not a short URL
        }

        // Strong penalty for TYPO3 system paths
        if (preg_match('/(typo3|fileadmin|typo3conf|typo3temp|uploads)/i', $input)) {
            return 0; // System path
        }

        // Penalty for too long without structure
        if ($len > 30 && !str_contains($input, '/') && !str_contains($input, '-')) {
            return 10; // Probably random string
        }

        return max(0, min(100, $score));
    }

    /**
     * Export heuristic data for caching
     */
    public function export(): array
    {
        return [
            'prefixTree' => $this->prefixTree,
            'commonPrefixes' => $this->commonPrefixes,
            'requiredSegments' => $this->requiredSegments,
            'characterClass' => $this->characterClass,
            'minLength' => $this->minLength,
            'maxLength' => $this->maxLength,
            'quickRejectRegex' => $this->quickRejectRegex,
            'quickAcceptRegex' => $this->quickAcceptRegex,
        ];
    }

    /**
     * Import heuristic data from cache
     */
    public static function import(array $data): self
    {
        $heuristic = new self();
        foreach ($data as $key => $value) {
            $heuristic->$key = $value;
        }
        return $heuristic;
    }
}

/**
 * Middleware integration example
 */
class ShortUrlMiddleware
{
    private PatternHeuristic $heuristic;
    private array $compiledPatterns;
    private int $threshold;

    public function __construct(array $cachedData, int $threshold = 50)
    {
        // Load pre-compiled heuristic from cache
        $this->heuristic = PatternHeuristic::import($cachedData['heuristic']);
        $this->compiledPatterns = $cachedData['patterns'];
        $this->threshold = $threshold;
    }

    public function shouldProcess(string $path): bool
    {
        // Ultra-fast heuristic check
        $score = $this->heuristic->couldMatch($path);
        return $score >= $this->threshold;
    }

    public function process(string $path): ?array
    {
        // Only called if shouldProcess() returns true
        foreach ($this->compiledPatterns as $pattern) {
            if ($result = $pattern->match($path)) {
                return $result->toArray();
            }
        }
        return null;
    }
}

/**
 * Cache builder - runs during deployment
 */
class PatternCacheBuilder
{
    public static function buildCache(array $patternStrings, string $cacheFile): void
    {
        $compiler = new PatternCompiler();
        $patterns = [];

        // Compile all patterns
        foreach ($patternStrings as $key => $pattern) {
            $patterns[$key] = $compiler->compile($pattern);
        }

        // Build heuristic
        $heuristic = PatternHeuristic::buildFromPatterns($patternStrings);

        // Create cache data
        $cacheData = [
            'generated' => date('Y-m-d H:i:s'),
            'heuristic' => $heuristic->export(),
            'patterns' => $patterns,
            // Optional: include serialized ASTs if needed
        ];

        // Write cache file
        $export = var_export($cacheData, true);
        $content = "<?php\n// Auto-generated pattern cache\nreturn $export;";
        file_put_contents($cacheFile, $content);
    }
}

// Example usage
function demonstrateHeuristic(): void
{
    $patterns = [
        "PAGE{uid:int}(-{langId:int})?",
        "NEWS{id:int}(-{slug:slug})?",
        "PROD{sku:alnum}(-{variant:alpha})?",
        "user/{username:alnum}/profile",
        "p/{shortcode:alnum}",
        "go/{campaign:slug}",
    ];

    $heuristic = PatternHeuristic::buildFromPatterns($patterns);

    // Test various inputs
    $tests = [
        "PAGE123",           // Should score high (95+)
        "PAGE123-1",         // Should score high (95+)
        "NEWS456",           // Should score high (95+)
        "user/john/profile", // Should score high (95+)
        "p/abc123",          // Should score high (95+)
        "/typo3/backend",    // Should score low (10-20)
        "index.html",        // Should score low (10-20)
        "fileadmin/image.jpg", // Should score very low (0-10)
        "random-string",     // Should score medium (40-60)
        "PAGE",              // Should score high (has prefix)
        "TOOLONGSTRINGTHATWOULDNEVERMATCHWITHOURPATTERNS", // Should score 0
    ];

    echo "Heuristic Analysis:\n";
    echo "==================\n\n";

    foreach ($tests as $test) {
        $t = microtime(true);
        $score = $heuristic->couldMatch($test);
        $verdict = $score >= 50 ? "✓ PROCESS" : "✗ SKIP";
        $t = (microtime(true) - $t) * 1000;
        printf("(in %s ms)%-30s Score: %3d%% %s\n", (string)$t, $test, $score, $verdict);
    }
}

// Run demonstration
demonstrateHeuristic();
