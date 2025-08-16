<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Config\Ast;

use CPSIT\ShortNr\Config\Ast\PatternBuilder;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternException;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the unified sequence architecture where:
 * 1. Only SubSequences handle optionality
 * 2. {group}? is normalized to ({group}) during parsing
 * 3. Every sequence requires ALL children to be satisfied
 * 4. SubSequences have all-or-nothing satisfaction logic
 */
class UnifiedSequenceArchitectureTest extends TestCase
{
    private PatternBuilder $patternBuilder;

    protected function setUp(): void
    {
        $this->patternBuilder = new PatternBuilder(new TypeRegistry());
    }

    #[DataProvider('syntaxNormalizationProvider')]
    public function testSyntaxNormalization(string $pattern, string $input, array $expectedValues): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        $this->assertNotNull($result, "Pattern '$pattern' should match input '$input' after normalization");
        
        foreach ($expectedValues as $key => $expectedValue) {
            $this->assertSame($expectedValue, $result->get($key), "Value for key '$key' should match expected");
        }
    }

    #[DataProvider('sequenceSatisfactionProvider')]
    public function testSequenceSatisfactionRules(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Pattern '$pattern' should match input '$input' - all required sequence elements should be satisfied");
            
            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key), "Value for key '$key' should match expected");
            }
        } else {
            $this->assertNull($result, "Pattern '$pattern' should NOT match input '$input' - sequence satisfaction failed");
        }
    }

    #[DataProvider('subSequenceAllOrNothingProvider')]
    public function testSubSequenceAllOrNothingLogic(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Pattern '$pattern' should match input '$input' - SubSequence all-or-nothing satisfied");
            
            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key), "Value for key '$key' should match expected");
            }
        } else {
            $this->assertNull($result, "Pattern '$pattern' should NOT match input '$input' - SubSequence all-or-nothing failed");
        }
    }

    #[DataProvider('nestedSubSequenceCascadingProvider')]
    public function testNestedSubSequenceCascadingFailure(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Pattern '$pattern' should match input '$input' - nested SubSequence logic correct");
            
            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key), "Value for key '$key' should match expected");
            }
        } else {
            $this->assertNull($result, "Pattern '$pattern' should NOT match input '$input' - cascading failure occurred");
        }
    }

    #[DataProvider('greedyAdjacentValidationProvider')]
    public function testGreedyAdjacentValidationAfterNormalization(string $pattern, bool $shouldCompile): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        
        if (!$shouldCompile) {
            $this->expectException(ShortNrPatternException::class);
        }
        
        $compiled = $compiler->compile($pattern);
        
        if ($shouldCompile) {
            $this->assertNotNull($compiled, "Pattern '$pattern' should compile successfully after normalization");
        }
    }

    #[DataProvider('complexSequenceHierarchyProvider')]
    public function testComplexSequenceHierarchy(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Complex hierarchy pattern '$pattern' should match input '$input'");
            
            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key), "Value for key '$key' should match expected");
            }
        } else {
            $this->assertNull($result, "Complex hierarchy pattern '$pattern' should NOT match input '$input'");
        }
    }

    // Data Providers

    public static function syntaxNormalizationProvider(): Generator
    {
        // Test that {group}? is normalized to ({group}) and behaves identically
        yield 'single-optional-group-normalized' => [
            'PAGE{id:int}?', 
            'PAGE123', 
            ['id' => 123]
        ];
        
        yield 'single-optional-group-absent-normalized' => [
            'PAGE{id:int}?', 
            'PAGE', 
            ['id' => null]
        ];
        
        yield 'multiple-optional-groups-normalized' => [
            'USER-{name:str}?-{age:int}?', 
            'USER--25', 
            ['name' => null, 'age' => 25]
        ];
        
        yield 'mixed-required-optional-normalized' => [
            'PAGE{id:int}-{lang:str}?-{version:int}', 
            'PAGE123--456', 
            ['id' => 123, 'lang' => null, 'version' => 456]
        ];
        
        yield 'mixed-required-optional-some-missing-normalized' => [
            'PAGE{id:int}-{lang:str}?-{version:int}', 
            'PAGE123-en-456', 
            ['id' => 123, 'lang' => 'en', 'version' => 456]
        ];
    }

    public static function sequenceSatisfactionProvider(): Generator
    {
        // Root sequence: ALL children must be satisfied
        yield 'root-sequence-all-required-satisfied' => [
            'ABC{a:int}-{b:str}', 
            'ABC123-test', 
            true, 
            ['a' => 123, 'b' => 'test']
        ];
        
        yield 'root-sequence-missing-required-element' => [
            'ABC{a:int}-{b:str}', 
            'ABC123', 
            false
        ];
        
        yield 'root-sequence-with-optional-subsequence' => [
            'ABC{a:int}(-{b:str})', 
            'ABC123', 
            true, 
            ['a' => 123, 'b' => null]
        ];
        
        yield 'root-sequence-with-optional-subsequence-present' => [
            'ABC{a:int}(-{b:str})', 
            'ABC123-test', 
            true, 
            ['a' => 123, 'b' => 'test']
        ];
        
        // Literals always satisfy by default
        yield 'literals-always-satisfy' => [
            'PREFIX{a:int}MIDDLE{b:str}SUFFIX', 
            'PREFIX123MIDDLEtestSUFFIX', 
            true, 
            ['a' => 123, 'b' => 'test']
        ];
    }

    public static function subSequenceAllOrNothingProvider(): Generator
    {
        // SubSequence with multiple elements - ALL must be present
        yield 'subsequence-all-elements-present' => [
            'USER({name:str}-{age:int})', 
            'USERjohn-25', 
            true, 
            ['name' => 'john', 'age' => 25]
        ];
        
        yield 'subsequence-missing-second-element' => [
            'USER({name:str}-{age:int})', 
            'USERjohn', 
            false  // Should NOT match - john doesn't fit the SubSequence pattern, no group to capture it
        ];
        
        yield 'subsequence-partial-match-forbidden' => [
            'USER({name:str}-{age:int})', 
            'USERjohn-', 
            false  // Partial SubSequence match should fail
        ];
        
        // Complex SubSequence with literals
        yield 'subsequence-with-literals-all-present' => [
            'API(/v{version:int}/users)', 
            'API/v2/users', 
            true, 
            ['version' => 2]
        ];
        
        yield 'subsequence-with-literals-absent' => [
            'API(/v{version:int}/users)', 
            'API', 
            true, 
            ['version' => null]
        ];
        
        // Multiple SubSequences
        yield 'multiple-subsequences-both-present' => [
            'BASE({a:int})({b:str})', 
            'BASE123test', 
            true, 
            ['a' => 123, 'b' => 'test']
        ];
        
        yield 'multiple-subsequences-first-only' => [
            'BASE({a:int})({b:str})', 
            'BASE123', 
            true, 
            ['a' => 123, 'b' => null]
        ];
        
        yield 'multiple-subsequences-second-only' => [
            'BASE({a:int})({b:str})', 
            'BASEtest', 
            true, 
            ['a' => null, 'b' => 'test']
        ];
    }

    public static function nestedSubSequenceCascadingProvider(): Generator
    {
        // The key test case from your example
        yield 'cascading-failure-example-valid-minimal' => [
            '{a:int}(-{b:int}-{c:int}(-{d:int}))', 
            '123', 
            true, 
            ['a' => 123, 'b' => null, 'c' => null, 'd' => null]
        ];
        
        yield 'cascading-failure-example-valid-outer-complete' => [
            '{a:int}(-{b:int}-{c:int}(-{d:int}))', 
            '123-456-789', 
            true, 
            ['a' => 123, 'b' => 456, 'c' => 789, 'd' => null]
        ];
        
        yield 'cascading-failure-example-valid-all-complete' => [
            '{a:int}(-{b:int}-{c:int}(-{d:int}))', 
            '123-456-789-101', 
            true, 
            ['a' => 123, 'b' => 456, 'c' => 789, 'd' => 101]
        ];
        
        yield 'cascading-failure-example-invalid-partial' => [
            '{a:int}(-{b:int}-{c:int}(-{d:int}))', 
            '123-456', 
            false  // b present but c missing -> entire outer SubSequence fails -> inner SubSequence also destroyed
        ];
        
        // More cascading examples
        yield 'triple-nesting-complete' => [
            'A({b:int}(-{c:str}(-{d:int})))', 
            'A123-test-456', 
            true, 
            ['b' => 123, 'c' => 'test', 'd' => 456]
        ];
        
        yield 'triple-nesting-middle-level-only' => [
            'A({b:int}(-{c:str}(-{d:int})))', 
            'A123-test', 
            true, 
            ['b' => 123, 'c' => 'test', 'd' => null]
        ];
        
        yield 'triple-nesting-outer-level-only' => [
            'A({b:int}(-{c:str}(-{d:int})))', 
            'A123', 
            true, 
            ['b' => 123, 'c' => null, 'd' => null]
        ];
        
        yield 'triple-nesting-partial-failure' => [
            'A({b:int}(-{c:str}(-{d:int})))', 
            'A123-', 
            false  // c is empty/missing, so middle SubSequence fails, destroying inner SubSequence
        ];
    }

    public static function greedyAdjacentValidationProvider(): Generator
    {
        // After normalization, these should be forbidden (adjacent greedy groups in same sequence)
        yield 'forbidden-adjacent-greedy-after-normalization' => ['{a:int}{b:int}', false];
        yield 'forbidden-mixed-types-adjacent' => ['{id:int}{name:str}', false];
        
        // After normalization, these should be allowed (SubSequences break adjacency)
        yield 'allowed-normalized-optional-groups' => ['{a:int}?{b:int}?', true]; // Becomes ({a:int})({b:int})
        yield 'allowed-explicit-subsequences' => ['({a:int})({b:int})', true];
        yield 'allowed-literal-separator' => ['{a:int}-{b:int}', true];
        
        // CONSTRAINTS DON'T AFFECT GREEDINESS - these are still forbidden
        yield 'forbidden-constrained-still-adjacent' => ['{a:int(max=999)}{b:int}', false];
        
        // Mixed scenarios - this one is forbidden because {b:int}{c:str} are adjacent
        yield 'forbidden-subsequence-then-adjacent' => ['{a:int}?-{b:int}{c:str}', false];
    }

    public static function complexSequenceHierarchyProvider(): Generator
    {
        // Complex real-world patterns testing the full architecture
        yield 'blog-url-with-optional-elements' => [
            '/blog/{year:int}(-/{month:int}(-/{day:int}(-/{slug:str})))', 
            '/blog/2024-/03-/15-/my-post', 
            true, 
            ['year' => 2024, 'month' => 3, 'day' => 15, 'slug' => 'my-post']
        ];
        
        yield 'blog-url-minimal' => [
            '/blog/{year:int}(-/{month:int}(-/{day:int}(-/{slug:str})))', 
            '/blog/2024', 
            true, 
            ['year' => 2024, 'month' => null, 'day' => null, 'slug' => null]
        ];
        
        yield 'blog-url-partial-invalid' => [
            '/blog/{year:int}(-/{month:int}(-/{day:int}(-/{slug:str})))', 
            '/blog/2024-/03', 
            false  // month SubSequence started but not completed
        ];
        
        // API endpoint with versioning and optional parameters
        yield 'api-endpoint-full' => [
            '/api/v{version:int}/{resource:str}(-/{id:int}(-/{action:str}))', 
            '/api/v2/users-/123-/edit', 
            true, 
            ['version' => 2, 'resource' => 'users', 'id' => 123, 'action' => 'edit']
        ];
        
        yield 'api-endpoint-resource-only' => [
            '/api/v{version:int}/{resource:str}(-/{id:int}(-/{action:str}))', 
            '/api/v1/posts', 
            true, 
            ['version' => 1, 'resource' => 'posts', 'id' => null, 'action' => null]
        ];
        
        // E-commerce product URL
        yield 'ecommerce-product-full' => [
            '/shop/{category:str}(-/{subcategory:str})/{product:str}(-/variant-{variant:int})', 
            '/shop/electronics-/phones/iphone-/variant-2', 
            true, 
            ['category' => 'electronics', 'subcategory' => 'phones', 'product' => 'iphone', 'variant' => 2]
        ];
        
        yield 'ecommerce-product-no-subcategory' => [
            '/shop/{category:str}(-/{subcategory:str})/{product:str}(-/variant-{variant:int})', 
            '/shop/books/programming', 
            true, 
            ['category' => 'books', 'subcategory' => null, 'product' => 'programming', 'variant' => null]
        ];
    }
}