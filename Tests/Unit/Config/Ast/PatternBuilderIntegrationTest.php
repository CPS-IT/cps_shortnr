<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Config\Ast;

use CPSIT\ShortNr\Config\Ast\PatternBuilder;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Config\Ast\Heuristic\PatternHeuristic;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the entire pattern flow:
 * PatternBuilder -> PatternCompiler -> CompiledPattern -> match/generate
 * Tests behavior, not implementation details
 */
class PatternBuilderIntegrationTest extends TestCase
{
    private PatternBuilder $patternBuilder;

    protected function setUp(): void
    {
        $this->patternBuilder = new PatternBuilder(new TypeRegistry());
    }

    #[DataProvider('basicPatternMatchingProvider')]
    public function testBasicPatternMatching(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);

        $result = $compiled->match($input);

        if ($shouldMatch) {
            $this->assertNotNull($result, "Pattern '$pattern' should match input '$input'");
            $this->assertSame($input, $result->getInput());

            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key), "Value for key '$key' does not match");
            }
        } else {
            $this->assertNull($result, "Pattern '$pattern' should NOT match input '$input'");
        }
    }

    #[DataProvider('constraintValidationProvider')]
    public function testConstraintValidation(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);

        $result = $compiled->match($input);

        if ($shouldMatch) {
            $this->assertNotNull($result, "Pattern '$pattern' should match input '$input'");
            // Note: isFailed() has a typo in the source - should check errors directly
            $this->assertEmpty($result->getErrors(), "Match should not have constraint validation errors");

            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key));
            }
        } else {
            // Constraint violations return MatchResult with errors, not null
            if ($result !== null) {
                $this->assertNotEmpty($result->getErrors(), "Constraint violation should produce errors");
            } else {
                // Pattern doesn't match at regex level
                $this->assertNull($result, "Pattern '$pattern' should NOT match input '$input'");
            }
        }
    }

    #[DataProvider('optionalSectionsProvider')]
    public function testOptionalSections(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);

        $result = $compiled->match($input);

        if ($shouldMatch) {
            $this->assertNotNull($result, "Pattern '$pattern' should match input '$input'");

            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key));
            }
        } else {
            $this->assertNull($result, "Pattern '$pattern' should NOT match input '$input'");
        }
    }

    #[DataProvider('generationProvider')]
    public function testPatternGeneration(string $pattern, array $values, string $expectedOutput): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);

        $generated = $compiled->generate($values);

        $this->assertSame($expectedOutput, $generated, "Generated output does not match expected");

        // Verify round-trip: generate -> match -> same values
        $matchResult = $compiled->match($generated);
        $this->assertNotNull($matchResult, "Generated output should match the original pattern");

        foreach ($values as $key => $expectedValue) {
            $this->assertSame($expectedValue, $matchResult->get($key), "Round-trip failed for key '$key'");
        }
    }

    #[DataProvider('heuristicPreFilteringProvider')]
    public function testHeuristicPreFiltering(array $patterns, string $input, bool $shouldSupport): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiledPatterns = [];

        foreach ($patterns as $pattern) {
            $compiledPatterns[] = $compiler->compile($pattern);
        }

        $heuristic = PatternHeuristic::buildFromPatterns($compiledPatterns);

        $this->assertSame($shouldSupport, $heuristic->support($input),
            "Heuristic support check failed for input '$input'");
    }

    #[DataProvider('hydrationDehydrationProvider')]
    public function testHydrationDehydration(string $pattern, string $testInput): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $original = $compiler->compile($pattern);

        // Test dehydration
        $dehydrated = $compiler->dehydrate($original);
        $this->assertIsArray($dehydrated, "Dehydration should return an array");

        // Test hydration
        $rehydrated = $compiler->hydrate($dehydrated);

        // Test that rehydrated pattern behaves identically
        $originalResult = $original->match($testInput);
        $rehydratedResult = $rehydrated->match($testInput);

        if ($originalResult === null) {
            $this->assertNull($rehydratedResult, "Rehydrated pattern should also return null for non-matching input");
        } else {
            $this->assertNotNull($rehydratedResult, "Rehydrated pattern should match if original matched");
            $this->assertSame($originalResult->toArray(), $rehydratedResult->toArray(),
                "Rehydrated pattern should produce identical results");
        }
    }

    #[DataProvider('exoticPatternProvider')]
    public function testExoticPatternCombinations(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);

        $result = $compiled->match($input);

        if ($shouldMatch) {
            $this->assertNotNull($result, "Exotic pattern '$pattern' should match input '$input'");

            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key));
            }
        } else {
            $this->assertNull($result, "Exotic pattern '$pattern' should NOT match input '$input'");
        }
    }

    #[DataProvider('realWorldPatternsProvider')]
    public function testRealWorldPatterns(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);

        $result = $compiled->match($input);

        if ($shouldMatch) {
            $this->assertNotNull($result, "Real-world pattern '$pattern' should match input '$input'");

            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key));
            }
        } else {
            $this->assertNull($result, "Real-world pattern '$pattern' should NOT match input '$input'");
        }
    }

    // Data Providers

    public static function basicPatternMatchingProvider(): Generator
    {
        yield 'simple-literal' => ['PAGE', 'PAGE', true, []];
        yield 'simple-literal-fail' => ['PAGE', 'ARTICLE', false];

        yield 'single-int-group' => ['PAGE{uid:int}', 'PAGE123', true, ['uid' => 123]];
        yield 'single-int-group-fail' => ['PAGE{uid:int}', 'PAGEabc', false];

        yield 'single-string-group' => ['USER{name:str}', 'USERjohn', true, ['name' => 'john']];
        yield 'single-string-group-fail' => ['USER{name:str}', 'USER', false];

        yield 'multiple-groups' => ['PAGE{uid:int}-{lang:str}', 'PAGE123-en', true, ['uid' => 123, 'lang' => 'en']];
        yield 'multiple-groups-fail' => ['PAGE{uid:int}-{lang:str}', 'PAGE123', false];
    }

    public static function constraintValidationProvider(): Generator
    {
        yield 'int-min-valid' => ['PAGE{uid:int(min=1)}', 'PAGE1', true, ['uid' => 1]];
        yield 'int-min-valid-high' => ['PAGE{uid:int(min=1)}', 'PAGE999', true, ['uid' => 999]];
        yield 'int-min-invalid' => ['PAGE{uid:int(min=1)}', 'PAGE0', false];

        yield 'int-max-valid' => ['PAGE{uid:int(max=100)}', 'PAGE100', true, ['uid' => 100]];
        yield 'int-max-valid-low' => ['PAGE{uid:int(max=100)}', 'PAGE1', true, ['uid' => 1]];
        yield 'int-max-invalid' => ['PAGE{uid:int(max=100)}', 'PAGE101', false];

        yield 'int-range-valid' => ['PAGE{uid:int(min=10, max=99)}', 'PAGE50', true, ['uid' => 50]];
        yield 'int-range-below' => ['PAGE{uid:int(min=10, max=99)}', 'PAGE9', false];
        yield 'int-range-above' => ['PAGE{uid:int(min=10, max=99)}', 'PAGE100', false];

        yield 'int-default-used' => ['PAGE{uid:int(default=42)}?', 'PAGE', true, ['uid' => 42]];
        yield 'int-default-overridden' => ['PAGE{uid:int(default=42)}?', 'PAGE123', true, ['uid' => 123]];
    }

    public static function optionalSectionsProvider(): Generator
    {
        yield 'optional-group-present' => ['PAGE{uid:int}{lang:str}?', 'PAGE123en', true, ['uid' => 123, 'lang' => 'en']];
        yield 'optional-group-absent' => ['PAGE{uid:int}{lang:str}?', 'PAGE123', true, ['uid' => 123, 'lang' => null]];

        yield 'optional-subsequence-present' => ['PAGE{uid:int}(-{lang:str})', 'PAGE123-en', true, ['uid' => 123, 'lang' => 'en']];
        yield 'optional-subsequence-absent' => ['PAGE{uid:int}(-{lang:str})', 'PAGE123', true, ['uid' => 123, 'lang' => null]];

        yield 'nested-optional-all-present' => ['PAGE{uid:int}(-{lang:str}(-{variant:str}))', 'PAGE123-en-mobile', true, ['uid' => 123, 'lang' => 'en', 'variant' => 'mobile']];
        yield 'nested-optional-partial' => ['PAGE{uid:int}(-{lang:str}(-{variant:str}))', 'PAGE123-en', true, ['uid' => 123, 'lang' => 'en', 'variant' => null]];
        yield 'nested-optional-minimal' => ['PAGE{uid:int}(-{lang:str}(-{variant:str}))', 'PAGE123', true, ['uid' => 123, 'lang' => null, 'variant' => null]];
    }

    public static function generationProvider(): Generator
    {
        yield 'simple-generation' => ['PAGE{uid:int}', ['uid' => 123], 'PAGE123'];
        yield 'multi-group-generation' => ['PAGE{uid:int}-{lang:str}', ['uid' => 123, 'lang' => 'en'], 'PAGE123-en'];
        yield 'optional-with-value' => ['PAGE{uid:int}{lang:str}?', ['uid' => 123, 'lang' => 'en'], 'PAGE123en'];
        yield 'optional-without-value' => ['PAGE{uid:int}{lang:str}?', ['uid' => 123], 'PAGE123'];
        yield 'subsequence-with-value' => ['PAGE{uid:int}(-{lang:str})', ['uid' => 123, 'lang' => 'en'], 'PAGE123-en'];
        yield 'subsequence-without-value' => ['PAGE{uid:int}(-{lang:str})', ['uid' => 123], 'PAGE123'];
        
        // Type coercion during generation
        yield 'int-from-string' => ['PAGE{uid:int}', ['uid' => '123'], 'PAGE123'];
        yield 'string-from-int' => ['USER{name:str}', ['name' => 123], 'USER123'];
        
        // Special characters in generated values
        yield 'special-chars-in-value' => ['USER{name:str}', ['name' => 'john-doe'], 'USERjohn-doe'];
        yield 'underscore-in-value' => ['CODE{name:str}', ['name' => 'test_value'], 'CODEtest_value'];
        yield 'dot-in-value' => ['FILE{name:str}', ['name' => 'file.txt'], 'FILEfile.txt'];
        
        // Zero and empty values
        yield 'zero-int-value' => ['PAGE{uid:int}', ['uid' => 0], 'PAGE0'];
        yield 'empty-string-value' => ['PREFIX{suffix:str}', ['suffix' => ''], 'PREFIX'];
        
        // Unicode values
        yield 'unicode-in-string' => ['USER{name:str}', ['name' => 'jöhn'], 'USERjöhn'];
    }

    public static function heuristicPreFilteringProvider(): Generator
    {
        $patterns = ['PAGE{uid:int}', 'ARTICLE{id:int}', 'USER{name:str}'];

        yield 'heuristic-should-support-valid-prefix' => [$patterns, 'PAGE123', true];
        yield 'heuristic-should-support-another-valid-prefix' => [$patterns, 'ARTICLE456', true];
        yield 'heuristic-should-reject-invalid-prefix' => [$patterns, 'BLOG123', false];
        yield 'heuristic-should-reject-too-short' => [$patterns, 'P', false];
        yield 'heuristic-should-reject-too-long' => [$patterns, str_repeat('PAGE123', 100), false];
    }

    public static function hydrationDehydrationProvider(): Generator
    {
        yield 'simple-pattern' => ['PAGE{uid:int}', 'PAGE123'];
        yield 'complex-pattern' => ['PAGE{uid:int(min=1, max=9999)}(-{lang:str})', 'PAGE123-en'];
        yield 'optional-pattern' => ['PAGE{uid:int}{lang:str}?', 'PAGE123en'];
        yield 'non-matching-input' => ['PAGE{uid:int}', 'ARTICLE123'];
    }

    public static function exoticPatternProvider(): Generator
    {
        // Test edge cases and unusual but valid patterns
        yield 'special-chars-literal' => ['user.{id:int}', 'user.123', true, ['id' => 123]];
        yield 'dollar-sign-literal' => ['price: ${amount:int}', 'price: $50', true, ['amount' => 50]];
        yield 'multiple-optional-groups' => ['{a:int}?{b:int}?{c:int}?', '123', true, ['a' => 1, 'b' => 2, 'c' => 3]];
        yield 'multiple-optional-groups-partial' => ['{a:int}?{b:int}?{c:int}?', '12', true, ['a' => 1, 'b' => 2, 'c' => null]];
        yield 'multiple-optional-groups-minimal' => ['{a:int}?{b:int}?{c:int}?', '1', true, ['a' => 1, 'b' => null, 'c' => null]];

        yield 'deeply-nested-optionals' => ['A{a:int}(B{b:int}(C{c:int}(D{d:int})))', 'A1B2C3D4', true, ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]];
        yield 'deeply-nested-optionals-partial' => ['A{a:int}(B{b:int}(C{c:int}(D{d:int})))', 'A1B2', true, ['a' => 1, 'b' => 2, 'c' => null, 'd' => null]];

        yield 'mixed-constraints' => ['ITEM{id:int(min=1, max=999)}-{code:str(minLen=2, maxLen=5)}', 'ITEM123-ABC', true, ['id' => 123, 'code' => 'ABC']];
        yield 'mixed-constraints-fail-int' => ['ITEM{id:int(min=1, max=999)}-{code:str(minLen=2, maxLen=5)}', 'ITEM1000-ABC', false];
    }

    public static function realWorldPatternsProvider(): Generator
    {
        // Test all the provided example patterns
        yield 'PAGE-basic' => ['PAGE{uid:int(min=1)}', 'PAGE123', true, ['uid' => 123]];
        yield 'PAGE-with-lang' => ['PAGE{uid:int(min=1)}(-{sys_language_uid:int(min=0, max=5, default=0)})', 'PAGE123-2', true, ['uid' => 123, 'sys_language_uid' => 2]];
        yield 'PAGE-without-lang' => ['PAGE{uid:int(min=1)}(-{sys_language_uid:int(min=0, max=5, default=0)})', 'PAGE123', true, ['uid' => 123, 'sys_language_uid' => 0]];
        yield 'PAGE-lang-out-of-range' => ['PAGE{uid:int(min=1)}(-{sys_language_uid:int(min=0, max=5, default=0)})', 'PAGE123-6', false];

        yield 'FTE-with-title' => ['FTE{uid:int(min=1)}(-{sys_language_uid:int(min=0, max=5, default=0)}(+{title:str}))', 'FTE123-1+title', true, ['uid' => 123, 'sys_language_uid' => 1, 'title' => 'title']];
        yield 'FTE-without-title' => ['FTE{uid:int(min=1)}(-{sys_language_uid:int(min=0, max=5, default=0)}(+{title:str}))', 'FTE123-1', true, ['uid' => 123, 'sys_language_uid' => 1, 'title' => null]];
        yield 'FTE-minimal' => ['FTE{uid:int(min=1)}(-{sys_language_uid:int(min=0, max=5, default=0)}(+{title:str}))', 'FTE123', true, ['uid' => 123, 'sys_language_uid' => 0, 'title' => null]];

        yield 'VIDEO-basic' => ['VIDEO{uid:int(min=1)}(-{sys_language_uid:int(min=0, max=5, default=0)})', 'VIDEO456', true, ['uid' => 456, 'sys_language_uid' => 0]];
        yield 'EVENT-basic' => ['EVENT{uid:int(min=1)}(-{sys_language_uid:int(min=0, max=5, default=0)})', 'EVENT789', true, ['uid' => 789, 'sys_language_uid' => 0]];
        yield 'NEWS-basic' => ['NEWS{uid:int(min=1)}(-{sys_language_uid:int(min=0, max=5, default=0)})', 'NEWS321', true, ['uid' => 321, 'sys_language_uid' => 0]];

        // Test validation failures
        yield 'PAGE-uid-too-small' => ['PAGE{uid:int(min=1)}', 'PAGE0', false];
        yield 'PAGE-invalid-format' => ['PAGE{uid:int(min=1)}', 'PAGEabc', false];
        yield 'EVENT-wrong-prefix' => ['EVENT{uid:int(min=1)}', 'ARTICLE123', false];
    }
}
