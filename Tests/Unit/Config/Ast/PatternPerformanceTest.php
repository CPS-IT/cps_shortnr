<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Config\Ast;

use CPSIT\ShortNr\Config\Ast\PatternBuilder;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Config\Ast\Heuristic\PatternHeuristic;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Performance and stress tests for the pattern system
 * Tests scalability, memory usage, and performance characteristics
 */
class PatternPerformanceTest extends TestCase
{
    private PatternBuilder $patternBuilder;

    protected function setUp(): void
    {
        $this->patternBuilder = new PatternBuilder(new TypeRegistry());
    }

    #[DataProvider('massPatternProvider')]
    public function testMassPatternCompilation(array $patterns, int $expectedMinCount): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = [];
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        foreach ($patterns as $pattern) {
            $compiled[] = $compiler->compile($pattern);
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $this->assertGreaterThanOrEqual($expectedMinCount, count($compiled), "Should compile at least $expectedMinCount patterns");
        
        // Performance assertions (adjust thresholds based on your requirements)
        $compilationTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        
        $this->assertLessThan(5.0, $compilationTime, "Mass compilation should complete within 5 seconds");
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, "Should not use more than 50MB for compilation");
        
        // Test that all patterns work correctly
        foreach ($compiled as $index => $compiledPattern) {
            $testInput = $this->generateTestInputForPattern($patterns[$index]);
            if ($testInput !== null) {
                $result = $compiledPattern->match($testInput);
                // Just verify no crashes occur, result can be null for non-matching test inputs
                $this->assertTrue(true, "Pattern matching should not crash");
            }
        }
    }

    #[DataProvider('massMatchingProvider')]
    public function testMassMatching(string $pattern, array $inputs, int $expectedMatches): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $startTime = microtime(true);
        $matchCount = 0;
        
        foreach ($inputs as $input) {
            $result = $compiled->match($input);
            if ($result !== null) {
                $matchCount++;
            }
        }
        
        $endTime = microtime(true);
        $matchingTime = $endTime - $startTime;
        
        $this->assertSame($expectedMatches, $matchCount, "Expected match count does not match actual");
        $this->assertLessThan(2.0, $matchingTime, "Mass matching should complete within 2 seconds");
    }

    #[DataProvider('heuristicPerformanceProvider')]
    public function testHeuristicPerformance(array $patterns, array $testInputs, float $expectedRejectionRate): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiledPatterns = [];
        
        foreach ($patterns as $pattern) {
            $compiledPatterns[] = $compiler->compile($pattern);
        }
        
        $heuristic = PatternHeuristic::buildFromPatterns($compiledPatterns);
        
        $startTime = microtime(true);
        $supportCount = 0;
        
        foreach ($testInputs as $input) {
            if ($heuristic->support($input)) {
                $supportCount++;
            }
        }
        
        $endTime = microtime(true);
        $heuristicTime = $endTime - $startTime;
        
        $rejectionRate = 1 - ($supportCount / count($testInputs));
        
        $this->assertGreaterThanOrEqual($expectedRejectionRate - 0.1, $rejectionRate, "Heuristic should reject at least " . ($expectedRejectionRate * 100) . "% of invalid inputs");
        $this->assertLessThan(0.1, $heuristicTime, "Heuristic filtering should be very fast (< 100ms)");
    }

    #[DataProvider('serializationPerformanceProvider')]
    public function testSerializationPerformance(array $patterns): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = [];
        
        // Compile all patterns
        foreach ($patterns as $pattern) {
            $compiled[] = $compiler->compile($pattern);
        }
        
        // Test dehydration performance
        $startTime = microtime(true);
        $dehydrated = [];
        
        foreach ($compiled as $compiledPattern) {
            $dehydrated[] = $compiler->dehydrate($compiledPattern);
        }
        
        $dehydrationTime = microtime(true) - $startTime;
        
        // Test hydration performance
        $startTime = microtime(true);
        $rehydrated = [];
        
        foreach ($dehydrated as $data) {
            $rehydrated[] = $compiler->hydrate($data);
        }
        
        $hydrationTime = microtime(true) - $startTime;
        
        $this->assertLessThan(1.0, $dehydrationTime, "Dehydration should be fast");
        $this->assertLessThan(1.0, $hydrationTime, "Hydration should be fast");
        $this->assertCount(count($patterns), $rehydrated, "All patterns should be successfully rehydrated");
        
        // Verify functionality after serialization
        for ($i = 0; $i < count($compiled); $i++) {
            $testInput = $this->generateTestInputForPattern($patterns[$i]);
            if ($testInput !== null) {
                $originalResult = $compiled[$i]->match($testInput);
                $rehydratedResult = $rehydrated[$i]->match($testInput);
                
                if ($originalResult === null) {
                    $this->assertNull($rehydratedResult, "Rehydrated pattern should match original behavior");
                } else {
                    $this->assertNotNull($rehydratedResult, "Rehydrated pattern should match original behavior");
                    $this->assertEquals($originalResult->toArray(), $rehydratedResult->toArray(), "Results should be identical");
                }
            }
        }
    }

    #[DataProvider('memoryUsageProvider')]
    public function testMemoryUsage(string $pattern, int $iterations): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $startMemory = memory_get_usage();
        
        // Perform many operations to check for memory leaks
        for ($i = 0; $i < $iterations; $i++) {
            $testInput = $this->generateTestInputForPattern($pattern) ?? 'TEST123';
            $result = $compiled->match($testInput);
            
            if ($result !== null) {
                $result->toArray(); // Force processing
                $compiled->generate($this->extractValuesFromResult($result));
            }
        }
        
        $endMemory = memory_get_usage();
        $memoryIncrease = $endMemory - $startMemory;
        
        // Should not have significant memory increase (allowing for some variance)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, "Memory usage should not increase significantly during operations");
    }

    #[DataProvider('complexPatternStressProvider')]
    public function testComplexPatternStress(string $pattern, array $testInputs): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $startTime = microtime(true);
        $processedCount = 0;
        
        foreach ($testInputs as $input) {
            $result = $compiled->match($input);
            $processedCount++;
            
            // Test generation for successful matches
            if ($result !== null && !$result->isFailed()) {
                $values = $this->extractValuesFromResult($result);
                $generated = $compiled->generate($values);
                
                // Verify round-trip
                $roundTripResult = $compiled->match($generated);
                $this->assertNotNull($roundTripResult, "Round-trip should work for generated output");
            }
        }
        
        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;
        
        $this->assertSame(count($testInputs), $processedCount, "All inputs should be processed");
        $this->assertLessThan(10.0, $processingTime, "Complex pattern processing should complete within reasonable time");
    }

    // Helper methods

    private function generateTestInputForPattern(string $pattern): ?string
    {
        // Simple heuristic to generate test input based on pattern
        if (str_contains($pattern, 'PAGE{uid:int}')) {
            return 'PAGE123';
        }
        if (str_contains($pattern, 'USER{name:str}')) {
            return 'USERjohn';
        }
        if (str_contains($pattern, 'ARTICLE{id:int}')) {
            return 'ARTICLE456';
        }
        
        // For more complex patterns, return a reasonable default
        return preg_replace('/\{[^}]+\}/', '123', $pattern);
    }

    private function extractValuesFromResult($result): array
    {
        $values = [];
        foreach ($result->getGroups() as $name => $data) {
            $values[$name] = $data['value'];
        }
        return $values;
    }

    // Data Providers

    public static function massPatternProvider(): Generator
    {
        $basicPatterns = [];
        $complexPatterns = [];
        
        // Generate many basic patterns
        $prefixes = ['PAGE', 'ARTICLE', 'USER', 'NEWS', 'EVENT', 'VIDEO', 'BLOG', 'FORUM', 'WIKI', 'DOCS'];
        foreach ($prefixes as $prefix) {
            $basicPatterns[] = "{$prefix}{uid:int}";
            $basicPatterns[] = "{$prefix}{uid:int}(-{lang:str})";
            $basicPatterns[] = "{$prefix}{uid:int(min=1)}(-{sys_language_uid:int(min=0, max=5, default=0)})";
        }
        
        // Generate complex patterns
        foreach ($prefixes as $prefix) {
            $complexPatterns[] = "{$prefix}{uid:int(min=1, max=9999)}(-{lang:str(minLen=2, maxLen=5)}(-{variant:str}))";
            $complexPatterns[] = "{$prefix}{id:int}(-{category:str}(+{title:str}(#{tag:str})))";
        }
        
        yield 'basic-patterns-50' => [$basicPatterns, 30];
        yield 'complex-patterns-20' => [$complexPatterns, 20];
        yield 'mixed-patterns-100' => [array_merge($basicPatterns, $complexPatterns), 50];
    }

    public static function massMatchingProvider(): Generator
    {
        $inputs1000 = [];
        for ($i = 1; $i <= 1000; $i++) {
            $inputs1000[] = "PAGE{$i}";
        }
        
        $mixedInputs = [];
        for ($i = 1; $i <= 500; $i++) {
            $mixedInputs[] = "PAGE{$i}";          // Should match
            $mixedInputs[] = "INVALID{$i}";       // Should not match
        }
        
        yield 'page-pattern-1000-matches' => ['PAGE{uid:int}', $inputs1000, 1000];
        yield 'page-pattern-mixed-500-matches' => ['PAGE{uid:int}', $mixedInputs, 500];
    }

    public static function heuristicPerformanceProvider(): Generator
    {
        $patterns = ['PAGE{uid:int}', 'ARTICLE{id:int}', 'USER{name:str}'];
        
        $testInputs = [];
        // Add valid inputs (should be supported)
        for ($i = 1; $i <= 100; $i++) {
            $testInputs[] = "PAGE{$i}";
            $testInputs[] = "ARTICLE{$i}";
            $testInputs[] = "USER{$i}";
        }
        
        // Add invalid inputs (should be rejected by heuristic)
        for ($i = 1; $i <= 700; $i++) {
            $testInputs[] = "INVALID{$i}";
            $testInputs[] = "BLOG{$i}";
            $testInputs[] = "WRONG{$i}";
            $testInputs[] = str_repeat('X', 100);  // Too long
            $testInputs[] = "A";                   // Too short
        }
        
        // Expected rejection rate: ~70% (700/1000 invalid inputs)
        yield 'mixed-inputs-high-rejection' => [$patterns, $testInputs, 0.7];
    }

    public static function serializationPerformanceProvider(): Generator
    {
        $patterns50 = [];
        for ($i = 1; $i <= 50; $i++) {
            $patterns50[] = "PATTERN{$i}{uid:int(min=1, max=999)}(-{lang:str})";
        }
        
        $complexPatterns = [];
        for ($i = 1; $i <= 20; $i++) {
            $complexPatterns[] = "COMPLEX{$i}{a:int}(-{b:str}(+{c:int}(#{d:str})))";
        }
        
        yield 'patterns-50' => [$patterns50];
        yield 'complex-patterns-20' => [$complexPatterns];
    }

    public static function memoryUsageProvider(): Generator
    {
        yield 'simple-pattern-1000-iterations' => ['PAGE{uid:int}', 1000];
        yield 'complex-pattern-500-iterations' => ['PAGE{uid:int(min=1, max=999)}(-{lang:str}(-{variant:str}))', 500];
        yield 'optional-heavy-pattern-300-iterations' => ['{a:int}?{b:int}?{c:int}?{d:int}?', 300];
    }

    public static function complexPatternStressProvider(): Generator
    {
        $deeplyNestedInputs = [];
        for ($i = 1; $i <= 100; $i++) {
            $deeplyNestedInputs[] = "A{$i}B{$i}C{$i}D{$i}";        // Full match
            $deeplyNestedInputs[] = "A{$i}B{$i}";                   // Partial match
            $deeplyNestedInputs[] = "A{$i}";                        // Minimal match
            $deeplyNestedInputs[] = "INVALID{$i}";                  // No match
        }
        
        $multiOptionalInputs = [];
        for ($i = 1; $i <= 100; $i++) {
            $multiOptionalInputs[] = "{$i}";                        // Single group
            $multiOptionalInputs[] = "{$i}{$i}";                    // Two groups
            $multiOptionalInputs[] = "{$i}{$i}{$i}";                // Three groups
            $multiOptionalInputs[] = "{$i}{$i}{$i}{$i}{$i}";        // All groups
        }
        
        yield 'deeply-nested-pattern' => ['A{a:int}(B{b:int}(C{c:int}(D{d:int})))', $deeplyNestedInputs];
        yield 'multiple-optional-pattern' => ['{a:int}?{b:int}?{c:int}?{d:int}?{e:int}?', $multiOptionalInputs];
    }
}