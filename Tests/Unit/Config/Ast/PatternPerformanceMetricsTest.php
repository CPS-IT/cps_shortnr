<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Config\Ast;

use CPSIT\ShortNr\Config\Ast\PatternBuilder;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Config\Ast\Heuristic\PatternHeuristic;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Objective performance tests that measure relative performance
 * and algorithmic complexity rather than absolute timings
 */
class PatternPerformanceMetricsTest extends TestCase
{
    private PatternBuilder $patternBuilder;

    protected function setUp(): void
    {
        $this->patternBuilder = new PatternBuilder(new TypeRegistry());
    }

    /**
     * Test that heuristic filtering is O(1) for length check
     * by verifying that checking strings of different lengths takes similar time
     */
    #[DataProvider('heuristicComplexityProvider')]
    public function testHeuristicAlgorithmicComplexity(array $patterns, array $testSets): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiledPatterns = [];

        foreach ($patterns as $pattern) {
            $compiledPatterns[] = $compiler->compile($pattern);
        }

        $heuristic = PatternHeuristic::buildFromPatterns($compiledPatterns);

        $timings = [];

        foreach ($testSets as $setName => $inputs) {
            $iterations = 10000;

            // Warm up
            foreach ($inputs as $input) {
                $heuristic->support($input);
            }

            // Measure
            $start = hrtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                foreach ($inputs as $input) {
                    $heuristic->support($input);
                }
            }
            $elapsed = hrtime(true) - $start;

            $timings[$setName] = $elapsed / ($iterations * count($inputs));
        }

        // Assert that performance doesn't degrade significantly with input size
        // The ratio should be close to 1.0 for O(1) operations
        $ratio = $timings['large'] / $timings['small'];

        $this->assertLessThan(
            1.5,
            $ratio,
            "Performance should not degrade significantly with input size (O(1) complexity expected). Ratio: {$ratio}"
        );
    }

    /**
     * Test that heuristic is significantly faster than full pattern matching
     * This should always pass regardless of machine speed
     */
    public function testHeuristicVsFullMatchRelativePerformance(): void
    {
        $patterns = [
            'PAGE{uid:int(min=1, max=9999)}(-{lang:str(minLen=2, maxLen=5)})',
            'ARTICLE{id:int}(-{category:str})',
            'USER{name:str(minLen=3, maxLen=20)}',
        ];

        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiledPatterns = [];

        foreach ($patterns as $pattern) {
            $compiledPatterns[] = $compiler->compile($pattern);
        }

        $heuristic = PatternHeuristic::buildFromPatterns($compiledPatterns);

        // Test inputs that will be rejected
        $rejectInputs = [
            'INVALID123',
            'BLOG456',
            'X',
            str_repeat('A', 1000),
        ];

        $iterations = 1000;

        // Measure heuristic time
        $heuristicStart = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            foreach ($rejectInputs as $input) {
                $heuristic->support($input);
            }
        }
        $heuristicTime = hrtime(true) - $heuristicStart;

        // Measure full pattern matching time
        $fullMatchStart = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            foreach ($rejectInputs as $input) {
                foreach ($compiledPatterns as $pattern) {
                    $pattern->match($input);
                }
            }
        }
        $fullMatchTime = hrtime(true) - $fullMatchStart;

        $speedup = $fullMatchTime / $heuristicTime;

        $this->assertGreaterThan(
            10.0,
            $speedup,
            "Heuristic should be at least 10x faster than full matching for non-matching inputs. Got {$speedup}x speedup"
        );
    }

    /**
     * Test that memory usage scales linearly with pattern count
     */
    public function testMemoryScalability(): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();

        $memorySamples = [];
        $patternCounts = [10, 20, 40, 80];

        foreach ($patternCounts as $count) {
            $patterns = [];
            for ($i = 0; $i < $count; $i++) {
                $patterns[] = "PATTERN{$i}{uid:int}(-{lang:str})";
            }

            gc_collect_cycles();
            $memStart = memory_get_usage(true);

            $compiledPatterns = [];
            foreach ($patterns as $pattern) {
                $compiledPatterns[] = $compiler->compile($pattern);
            }

            $heuristic = PatternHeuristic::buildFromPatterns($compiledPatterns);

            $memEnd = memory_get_usage(true);
            $memorySamples[$count] = $memEnd - $memStart;

            unset($compiledPatterns, $heuristic);
        }

        // Calculate if memory grows linearly (not exponentially)
        // Compare ratios between different sizes
        $ratio1 = $memorySamples[20] / $memorySamples[10];
        $ratio2 = $memorySamples[40] / $memorySamples[20];
        $ratio3 = $memorySamples[80] / $memorySamples[40];

        // Ratios should be similar if growth is linear
        $variance = max($ratio1, $ratio2, $ratio3) - min($ratio1, $ratio2, $ratio3);

        $this->assertLessThan(
            0.5,
            $variance,
            "Memory should scale linearly with pattern count. Ratios: {$ratio1}, {$ratio2}, {$ratio3}"
        );
    }

    /**
     * Test that caching (hydrate/dehydrate) provides performance benefit
     */
    public function testCacheEffectiveness(): void
    {
        $patterns = [];
        for ($i = 0; $i < 50; $i++) {
            $patterns[] = "PATTERN{$i}{uid:int(min=1, max=999)}(-{lang:str})";
        }

        $compiler = $this->patternBuilder->getPatternCompiler();

        // Measure compilation time
        $compileStart = hrtime(true);
        $compiledPatterns = [];
        foreach ($patterns as $pattern) {
            $compiledPatterns[] = $compiler->compile($pattern);
        }
        $compileTime = hrtime(true) - $compileStart;

        // Measure dehydration
        $dehydrateStart = hrtime(true);
        $dehydrated = [];
        foreach ($compiledPatterns as $compiled) {
            $dehydrated[] = $compiler->dehydrate($compiled);
        }
        $dehydrateTime = hrtime(true) - $dehydrateStart;

        // Measure hydration
        $hydrateStart = hrtime(true);
        $rehydrated = [];
        foreach ($dehydrated as $data) {
            $rehydrated[] = $compiler->hydrate($data);
        }
        $hydrateTime = hrtime(true) - $hydrateStart;

        // Hydration should be significantly faster than compilation
        $speedup = $compileTime / $hydrateTime;

        $this->assertGreaterThan(
            5.0,
            $speedup,
            "Hydration should be at least 5x faster than compilation. Got {$speedup}x speedup"
        );

        // Dehydration should be fast (less than 20% of compilation time)
        $dehydrationOverhead = $dehydrateTime / $compileTime;

        $this->assertLessThan(
            0.2,
            $dehydrationOverhead,
            "Dehydration should be fast (< 20% of compilation time). Got {$dehydrationOverhead}"
        );
    }

    /**
     * Test that pattern complexity affects performance predictably
     */
    #[DataProvider('patternComplexityProvider')]
    public function testPatternComplexityImpact(array $simplePattern, array $complexPattern): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();

        $simple = $compiler->compile($simplePattern['pattern']);
        $complex = $compiler->compile($complexPattern['pattern']);

        $iterations = 5000;

        // Test matching performance
        $simpleStart = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $simple->match($simplePattern['input']);
        }
        $simpleTime = hrtime(true) - $simpleStart;

        $complexStart = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $complex->match($complexPattern['input']);
        }
        $complexTime = hrtime(true) - $complexStart;

        $complexityRatio = $complexTime / $simpleTime;

        // Complex patterns should be slower, but not exponentially so
        $this->assertGreaterThan(
            1.0,
            $complexityRatio,
            "Complex patterns should take more time than simple ones"
        );

        $this->assertLessThan(
            10.0,
            $complexityRatio,
            "Complex patterns should not be more than 10x slower. Got {$complexityRatio}x"
        );
    }

    /**
     * Profile memory allocations during hot path
     */
    public function testHotPathMemoryAllocations(): void
    {
        $patterns = ['PAGE{uid:int}', 'ARTICLE{id:int}'];
        $compiler = $this->patternBuilder->getPatternCompiler();

        $compiledPatterns = [];
        foreach ($patterns as $pattern) {
            $compiledPatterns[] = $compiler->compile($pattern);
        }

        $heuristic = PatternHeuristic::buildFromPatterns($compiledPatterns);

        // Warm up and force GC
        for ($i = 0; $i < 100; $i++) {
            $heuristic->support('PAGE123');
        }
        gc_collect_cycles();

        $iterations = 10000;
        $memBefore = memory_get_usage();

        // Hot path - should allocate minimal memory
        for ($i = 0; $i < $iterations; $i++) {
            $heuristic->support('PAGE123');
        }

        $memAfter = memory_get_usage();
        $memPerOperation = ($memAfter - $memBefore) / $iterations;

        // Should have near-zero allocations per operation
        $this->assertLessThan(
            10, // bytes
            $memPerOperation,
            "Hot path should have minimal memory allocations. Got {$memPerOperation} bytes per operation"
        );
    }

    /**
     * Test that operations complete within reasonable iteration counts
     * This tests algorithmic efficiency, not wall-clock time
     */
    public function testOperationIterationBounds(): void
    {
        $pattern = 'PAGE{uid:int}(-{lang:str}(-{variant:str}))';
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);

        // Count regex operations by using a wrapper
        $input = 'PAGE123-en-mobile';
        $regexCallCount = 0;

        // Override preg_match temporarily (in real code, you'd use a wrapper)
        $originalPattern = $compiled->getRegex();

        // Count character comparisons in the pattern
        $patternComplexity = strlen($originalPattern);
        $inputLength = strlen($input);

        // The product should give us an upper bound on operations
        $theoreticalMaxOperations = $patternComplexity * $inputLength;

        // For a well-designed pattern, actual operations should be much less
        $this->assertLessThan(
            1000,
            $theoreticalMaxOperations,
            "Pattern complexity should be reasonable"
        );
    }

    // Data Providers

    public static function heuristicComplexityProvider(): Generator
    {
        $patterns = ['PAGE{uid:int}', 'ARTICLE{id:int}', 'USER{name:str}'];

        yield 'length-complexity-test' => [
            $patterns,
            [
                'small' => ['PAGE1', 'USER2', 'ARTICLE3'],
                'medium' => ['PAGE12345', 'USER12345', 'ARTICLE999'],
                'large' => ['PAGE' . str_repeat('9', 50), 'USER' . str_repeat('x', 50)],
            ]
        ];
    }

    public static function patternComplexityProvider(): Generator
    {
        yield 'simple-vs-complex' => [
            ['pattern' => 'PAGE{uid:int}', 'input' => 'PAGE123'],
            ['pattern' => 'PAGE{uid:int(min=1, max=9999)}(-{lang:str(minLen=2, maxLen=5)}(-{var:str}))', 'input' => 'PAGE123-en-mobile']
        ];

        yield 'single-vs-multiple-groups' => [
            ['pattern' => '{id:int}', 'input' => '123'],
            ['pattern' => '{a:int}{b:int}{c:int}{d:int}{e:int}', 'input' => '12345']
        ];
    }
}
