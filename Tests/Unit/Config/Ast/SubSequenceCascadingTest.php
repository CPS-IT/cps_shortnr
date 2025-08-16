<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Config\Ast;

use CPSIT\ShortNr\Config\Ast\PatternBuilder;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Focused tests for SubSequence all-or-nothing logic and cascading failure behavior
 * 
 * Key Rules:
 * 1. Every sequence requires ALL child elements to be satisfied
 * 2. SubSequences are optional as units but have all-or-nothing satisfaction
 * 3. If ANY required element in a SubSequence fails, the entire SubSequence collapses
 * 4. Nested SubSequences are destroyed when their parent SubSequence fails
 */
class SubSequenceCascadingTest extends TestCase
{
    private PatternBuilder $patternBuilder;

    protected function setUp(): void
    {
        $this->patternBuilder = new PatternBuilder(new TypeRegistry());
    }

    #[DataProvider('basicSubSequenceProvider')]
    public function testBasicSubSequenceAllOrNothing(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Pattern '$pattern' should match input '$input'");
            
            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key), "Value for key '$key' should match expected");
            }
        } else {
            $this->assertNull($result, "Pattern '$pattern' should NOT match input '$input' due to SubSequence all-or-nothing rule");
        }
    }

    #[DataProvider('cascadingFailureProvider')]
    public function testCascadingFailureDestroysNestedSubSequences(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Pattern '$pattern' should match input '$input'");
            
            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key), "Value for key '$key' should match expected");
            }
        } else {
            $this->assertNull($result, "Pattern '$pattern' should NOT match input '$input' due to cascading SubSequence failure");
        }
    }

    #[DataProvider('complexNestingProvider')]
    public function testComplexNestedSubSequenceLogic(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Complex nested pattern '$pattern' should match input '$input'");
            
            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key), "Value for key '$key' should match expected");
            }
        } else {
            $this->assertNull($result, "Complex nested pattern '$pattern' should NOT match input '$input'");
        }
    }

    #[DataProvider('realWorldCascadingProvider')]
    public function testRealWorldCascadingScenarios(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Real-world pattern '$pattern' should match input '$input'");
            
            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key), "Value for key '$key' should match expected");
            }
        } else {
            $this->assertNull($result, "Real-world pattern '$pattern' should NOT match input '$input'");
        }
    }

    // Data Providers

    public static function basicSubSequenceProvider(): Generator
    {
        // Simple SubSequence - all elements must be present
        yield 'basic-subsequence-complete' => [
            'USER({name:str}-{age:int})', 
            'USERjohn-25', 
            true, 
            ['name' => 'john', 'age' => 25]
        ];
        
        yield 'basic-subsequence-entirely-absent' => [
            'USER({name:str}-{age:int})', 
            'USER', 
            true, 
            ['name' => null, 'age' => null]
        ];
        
        yield 'basic-subsequence-partial-failure' => [
            'USER({name:str}-{age:int})', 
            'USERjohn-', 
            false  // name present but age missing -> entire SubSequence fails
        ];
        
        yield 'basic-subsequence-partial-failure-reverse' => [
            'USER({name:str}-{age:int})', 
            'USER-25', 
            false  // age present but name missing -> entire SubSequence fails
        ];
        
        // Multiple elements in SubSequence
        yield 'multi-element-subsequence-complete' => [
            'API(/v{version:int}/users/{id:int})', 
            'API/v2/users/123', 
            true, 
            ['version' => 2, 'id' => 123]
        ];
        
        yield 'multi-element-subsequence-absent' => [
            'API(/v{version:int}/users/{id:int})', 
            'API', 
            true, 
            ['version' => null, 'id' => null]
        ];
        
        yield 'multi-element-subsequence-incomplete' => [
            'API(/v{version:int}/users/{id:int})', 
            'API/v2/users/', 
            false  // started SubSequence but missing id -> entire SubSequence fails
        ];
    }

    public static function cascadingFailureProvider(): Generator
    {
        // The primary example from your description
        yield 'primary-cascading-example-valid-minimal' => [
            '{a:int}(-{b:int}-{c:int}(-{d:int}))', 
            '123', 
            true, 
            ['a' => 123, 'b' => null, 'c' => null, 'd' => null]
        ];
        
        yield 'primary-cascading-example-valid-outer-complete' => [
            '{a:int}(-{b:int}-{c:int}(-{d:int}))', 
            '123-456-789', 
            true, 
            ['a' => 123, 'b' => 456, 'c' => 789, 'd' => null]
        ];
        
        yield 'primary-cascading-example-valid-all-complete' => [
            '{a:int}(-{b:int}-{c:int}(-{d:int}))', 
            '123-456-789-101', 
            true, 
            ['a' => 123, 'b' => 456, 'c' => 789, 'd' => 101]
        ];
        
        yield 'primary-cascading-example-invalid-partial' => [
            '{a:int}(-{b:int}-{c:int}(-{d:int}))', 
            '123-456', 
            false  // b=456 present but c missing -> outer SubSequence fails -> inner SubSequence with d also destroyed
        ];
        
        // Alternative cascading patterns
        yield 'cascading-with-literals-valid' => [
            'BASE({x:int}-data-{y:str}(-extra-{z:int}))', 
            'BASE123-data-test', 
            true, 
            ['x' => 123, 'y' => 'test', 'z' => null]
        ];
        
        yield 'cascading-with-literals-valid-complete' => [
            'BASE({x:int}-data-{y:str}(-extra-{z:int}))', 
            'BASE123-data-test-extra-456', 
            true, 
            ['x' => 123, 'y' => 'test', 'z' => 456]
        ];
        
        yield 'cascading-with-literals-invalid' => [
            'BASE({x:int}-data-{y:str}(-extra-{z:int}))', 
            'BASE123-data-', 
            false  // x present, literal present, but y missing -> outer SubSequence fails
        ];
        
        // Deep nesting (3 levels)
        yield 'triple-nesting-all-levels' => [
            'A({b:int}(-{c:str}(-{d:int})))', 
            'A123-test-456', 
            true, 
            ['b' => 123, 'c' => 'test', 'd' => 456]
        ];
        
        yield 'triple-nesting-middle-failure-destroys-inner' => [
            'A({b:int}(-{c:str}(-{d:int})))', 
            'A123-', 
            false  // b present but c missing -> middle SubSequence fails -> inner SubSequence destroyed
        ];
    }

    public static function complexNestingProvider(): Generator
    {
        // Multiple independent SubSequences
        yield 'multiple-independent-subsequences-all' => [
            'ROOT({a:int})({b:str})({c:int})', 
            'ROOT123test456', 
            true, 
            ['a' => 123, 'b' => 'test', 'c' => 456]
        ];
        
        yield 'multiple-independent-subsequences-partial' => [
            'ROOT({a:int})({b:str})({c:int})', 
            'ROOT123test', 
            true, 
            ['a' => 123, 'b' => 'test', 'c' => null]
        ];
        
        yield 'multiple-independent-subsequences-none' => [
            'ROOT({a:int})({b:str})({c:int})', 
            'ROOT', 
            true, 
            ['a' => null, 'b' => null, 'c' => null]
        ];
        
        // Mixed required and SubSequence elements
        yield 'mixed-required-and-subsequences' => [
            '{req1:int}({opt1:str}){req2:int}({opt2:str})', 
            '123test456end', 
            true, 
            ['req1' => 123, 'opt1' => 'test', 'req2' => 456, 'opt2' => 'end']
        ];
        
        yield 'mixed-required-missing-fails' => [
            '{req1:int}({opt1:str}){req2:int}({opt2:str})', 
            '123test', 
            false  // req1 present, opt1 present, but req2 missing -> entire pattern fails
        ];
        
        // Complex branching
        yield 'complex-branching-pattern' => [
            'FILE{name:str}(-v{version:int}(-{branch:str}))(.{ext:str})', 
            'FILEtest-v2-dev.txt', 
            true, 
            ['name' => 'test', 'version' => 2, 'branch' => 'dev', 'ext' => 'txt']
        ];
        
        yield 'complex-branching-minimal' => [
            'FILE{name:str}(-v{version:int}(-{branch:str}))(.{ext:str})', 
            'FILEtest.txt', 
            true, 
            ['name' => 'test', 'version' => null, 'branch' => null, 'ext' => 'txt']
        ];
        
        yield 'complex-branching-partial-version-incomplete' => [
            'FILE{name:str}(-v{version:int}(-{branch:str}))(.{ext:str})', 
            'FILEtest-v.txt', 
            false  // version SubSequence started but version missing -> SubSequence fails
        ];
    }

    public static function realWorldCascadingProvider(): Generator
    {
        // E-commerce product URL with optional variants
        yield 'ecommerce-full-hierarchy' => [
            '/shop/{category:str}(-/{subcategory:str}(-/{brand:str}))/{product:str}(-/variant-{variant:int})', 
            '/shop/electronics-/phones-/apple/iphone-/variant-2', 
            true, 
            ['category' => 'electronics', 'subcategory' => 'phones', 'brand' => 'apple', 'product' => 'iphone', 'variant' => 2]
        ];
        
        yield 'ecommerce-category-only' => [
            '/shop/{category:str}(-/{subcategory:str}(-/{brand:str}))/{product:str}(-/variant-{variant:int})', 
            '/shop/books/programming', 
            true, 
            ['category' => 'books', 'subcategory' => null, 'brand' => null, 'product' => 'programming', 'variant' => null]
        ];
        
        yield 'ecommerce-incomplete-subcategory-fails' => [
            '/shop/{category:str}(-/{subcategory:str}(-/{brand:str}))/{product:str}(-/variant-{variant:int})', 
            '/shop/electronics-//iphone', 
            false  // subcategory SubSequence started but subcategory empty -> fails
        ];
        
        // Blog URL with date hierarchy
        yield 'blog-full-date-hierarchy' => [
            '/blog(-/{year:int}(-/{month:int}(-/{day:int})))/{post:str}', 
            '/blog-/2024-/03-/15/my-post', 
            true, 
            ['year' => 2024, 'month' => 3, 'day' => 15, 'post' => 'my-post']
        ];
        
        yield 'blog-no-date' => [
            '/blog(-/{year:int}(-/{month:int}(-/{day:int})))/{post:str}', 
            '/blog/general-post', 
            true, 
            ['year' => null, 'month' => null, 'day' => null, 'post' => 'general-post']
        ];
        
        yield 'blog-incomplete-date-fails' => [
            '/blog(-/{year:int}(-/{month:int}(-/{day:int})))/{post:str}', 
            '/blog-/2024-//my-post', 
            false  // year present but month missing in month SubSequence -> cascading failure
        ];
        
        // API endpoint with authentication and versioning
        yield 'api-with-auth-and-versioning' => [
            '/api(-/auth/{token:str})(/v{version:int})/{endpoint:str}(-/{id:int})', 
            '/api-/auth/abc123/v2/users-/456', 
            true, 
            ['token' => 'abc123', 'version' => 2, 'endpoint' => 'users', 'id' => 456]
        ];
        
        yield 'api-minimal' => [
            '/api(-/auth/{token:str})(/v{version:int})/{endpoint:str}(-/{id:int})', 
            '/api/posts', 
            true, 
            ['token' => null, 'version' => null, 'endpoint' => 'posts', 'id' => null]
        ];
        
        yield 'api-incomplete-auth-fails' => [
            '/api(-/auth/{token:str})(/v{version:int})/{endpoint:str}(-/{id:int})', 
            '/api-/auth//v1/users', 
            false  // auth SubSequence started but token missing -> fails
        ];
    }
}