<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Config\Ast;

use CPSIT\ShortNr\Config\Ast\PatternBuilder;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use CPSIT\ShortNr\Exception\ShortNrPatternException;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the new greediness validation rules
 * Validates that adjacent greedy groups are properly forbidden/allowed
 */
class GreedyPatternValidationTest extends TestCase
{
    private PatternBuilder $patternBuilder;

    protected function setUp(): void
    {
        $this->patternBuilder = new PatternBuilder(new TypeRegistry());
    }

    #[DataProvider('forbiddenGreedyPatternsProvider')]
    public function testForbiddenGreedyPatterns(string $pattern): void
    {
        $this->expectException(ShortNrPatternException::class);
        
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiler->compile($pattern);
    }

    #[DataProvider('allowedGreedyPatternsProvider')]
    public function testAllowedGreedyPatterns(string $pattern, string $input, array $expectedValues): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        $this->assertNotNull($result, "Pattern '$pattern' should compile and match input '$input'");
        
        foreach ($expectedValues as $key => $expectedValue) {
            $this->assertSame($expectedValue, $result->get($key), "Value for key '$key' should match expected");
        }
    }

    #[DataProvider('greedyVsNonGreedyProvider')]
    public function testGreedyVsNonGreedyBehavior(string $pattern, string $input, array $expectedValues): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        $this->assertNotNull($result, "Pattern '$pattern' should match input '$input'");
        
        foreach ($expectedValues as $key => $expectedValue) {
            $this->assertSame($expectedValue, $result->get($key), "Greediness behavior should produce expected value for '$key'");
        }
    }

    #[DataProvider('constraintEffectOnGreedinessProvider')]
    public function testConstraintEffectOnGreediness(string $pattern, string $input, array $expectedValues): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        $this->assertNotNull($result, "Constraint should make pattern valid: '$pattern'");
        
        foreach ($expectedValues as $key => $expectedValue) {
            $this->assertSame($expectedValue, $result->get($key), "Constraint should affect capture behavior for '$key'");
        }
    }

    // Data Providers

    public static function forbiddenGreedyPatternsProvider(): Generator
    {
        // Basic forbidden cases - adjacent greedy groups in same sequence
        yield 'two-ints-forbidden' => ['{a:int}{b:int}'];
        yield 'two-strings-forbidden' => ['{a:str}{b:str}'];
        yield 'int-then-string-forbidden' => ['{id:int}{name:str}'];
        yield 'string-then-int-forbidden' => ['{name:str}{id:int}'];
        
        // After normalization: {a:int}?{b:int} becomes ({a:int})({b:int}) - SubSequences break adjacency, so ALLOWED
        // This is moved to allowedGreedyPatternsProvider since it should be allowed
        
        // Inside same SubSequence - still forbidden
        yield 'forbidden-within-subsequence' => ['PAGE({a:int}{b:int})'];
        yield 'forbidden-within-nested-subsequence' => ['PAGE{id:int}(-{a:int}{b:int})'];
        
        // Multiple adjacent groups in same sequence
        yield 'three-greedy-groups' => ['{a:int}{b:int}{c:str}'];
        yield 'four-greedy-groups' => ['{a:int}{b:str}{c:int}{d:str}'];
        
        // Mixed with literals (still forbidden if groups are adjacent)
        yield 'prefix-then-forbidden-groups' => ['PAGE{a:int}{b:int}'];
        yield 'forbidden-groups-then-suffix' => ['{a:int}{b:int}SUFFIX'];
    }

    public static function allowedGreedyPatternsProvider(): Generator
    {
        // Literal separators break adjacency
        yield 'separated-by-dash' => ['{a:int}-{b:int}', '123-456', ['a' => 123, 'b' => 456]];
        yield 'separated-by-slash' => ['{a:int}/{b:str}', '123/abc', ['a' => 123, 'b' => 'abc']];
        yield 'separated-by-dot' => ['{a:str}.{b:int}', 'abc.123', ['a' => 'abc', 'b' => 123]];
        yield 'separated-by-space' => ['{a:int} {b:str}', '123 abc', ['a' => 123, 'b' => 'abc']];
        
        // First group constrained (non-greedy)
        yield 'first-constrained-max' => ['{a:int(max=999)}{b:int}', '123456', ['a' => 123, 'b' => 456]];
        yield 'first-constrained-range' => ['{a:int(min=1, max=999)}{b:str}', '123abc', ['a' => 123, 'b' => 'abc']];
        yield 'string-constrained-maxlen' => ['{name:str(maxLen=3)}{id:int}', 'abc123', ['name' => 'abc', 'id' => 123]];
        yield 'string-constrained-minmaxlen' => ['{name:str(minLen=2, maxLen=5)}{id:int}', 'hello123', ['name' => 'hello', 'id' => 123]];
        
        // Both groups constrained
        yield 'both-constrained' => ['{a:int(max=999)}{b:int(max=999)}', '123456', ['a' => 123, 'b' => 456]];
        yield 'mixed-both-constrained' => ['{name:str(maxLen=5)}{id:int(max=9999)}', 'hello123', ['name' => 'hello', 'id' => 123]];
        
        // Single groups (always allowed)
        yield 'single-int' => ['{id:int}', '123', ['id' => 123]];
        yield 'single-string' => ['{name:str}', 'test', ['name' => 'test']];
        
        // Non-adjacent groups (literal separators)
        yield 'non-adjacent-with-literal' => ['A{a:int}B{b:int}C', 'A123B456C', ['a' => 123, 'b' => 456]];
        
        // After normalization: {a:int}?{b:int} becomes ({a:int}){b:int} - SubSequence breaks adjacency
        yield 'normalized-optional-groups-allowed' => ['{a:int}?{b:int}', '123456', ['a' => 123, 'b' => 456]];
        yield 'explicit-subsequences' => ['({a:int})({b:str})', '123abc', ['a' => 123, 'b' => 'abc']];
        yield 'mixed-subsequence-and-required' => ['{a:int}({b:str}){c:int}', '123abc456', ['a' => 123, 'b' => 'abc', 'c' => 456]];
    }

    public static function greedyVsNonGreedyProvider(): Generator
    {
        // Demonstrate how constraints affect greediness
        yield 'unconstrained-greedy-captures-all' => [
            '{a:int}-rest', 
            '123456-rest', 
            ['a' => 123456]
        ];
        
        yield 'constrained-non-greedy-stops-early' => [
            '{a:int(max=999)}{b:int}', 
            '123456', 
            ['a' => 123, 'b' => 456]
        ];
        
        yield 'string-unconstrained-greedy' => [
            '{name:str}-suffix', 
            'verylongname-suffix', 
            ['name' => 'verylongname']
        ];
        
        yield 'string-constrained-non-greedy' => [
            '{name:str(maxLen=4)}{rest:str}', 
            'verylongname', 
            ['name' => 'very', 'rest' => 'longname']
        ];
        
        // Edge case: minimum length constraint
        yield 'string-min-length-still-greedy' => [
            '{name:str(minLen=3)}-{id:int}', 
            'verylongname-123', 
            ['name' => 'verylongname', 'id' => 123]
        ];
        
        yield 'int-min-constraint-still-greedy' => [
            '{id:int(min=100)}-{name:str}', 
            '123456-test', 
            ['id' => 123456, 'name' => 'test']
        ];
    }

    public static function constraintEffectOnGreedinessProvider(): Generator
    {
        // Show exactly which constraints make groups non-greedy
        yield 'max-makes-non-greedy' => [
            '{a:int(max=999)}{b:int}', 
            '123456', 
            ['a' => 123, 'b' => 456]
        ];
        
        yield 'min-max-makes-non-greedy' => [
            '{a:int(min=1, max=999)}{b:int}', 
            '123456', 
            ['a' => 123, 'b' => 456]
        ];
        
        yield 'maxlen-makes-string-non-greedy' => [
            '{name:str(maxLen=5)}{id:int}', 
            'hello123', 
            ['name' => 'hello', 'id' => 123]
        ];
        
        yield 'minlen-maxlen-makes-non-greedy' => [
            '{name:str(minLen=2, maxLen=4)}{rest:str}', 
            'testvalue', 
            ['name' => 'test', 'rest' => 'value']
        ];
        
        // Min-only or default-only constraints keep greediness
        yield 'min-only-stays-greedy-needs-separator' => [
            '{id:int(min=100)}-{name:str}', 
            '123456-test', 
            ['id' => 123456, 'name' => 'test']
        ];
        
        yield 'default-only-stays-greedy-needs-separator' => [
            '{id:int(default=42)}?-{name:str}', 
            '123-test', 
            ['id' => 123, 'name' => 'test']
        ];
        
        // Complex constraint combinations - first group capped to prevent starvation
        yield 'mixed-constraint-types' => [
            '{id:int(min=1, max=999)}{code:str(minLen=2, maxLen=5)}{flag:int(max=999)}', 
            '123ABC456', 
            ['id' => 123, 'code' => 'ABC', 'flag' => 456]
        ];
    }
}