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

    #[DataProvider('greedyBehaviorProvider')]
    public function testGreedyBehavior(string $pattern, string $input, array $expectedValues): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        $this->assertNotNull($result, "Pattern '$pattern' should match input '$input'");
        
        foreach ($expectedValues as $key => $expectedValue) {
            $this->assertSame($expectedValue, $result->get($key), "All types are greedy - expected value for '$key'");
        }
    }

    #[DataProvider('constraintValidationProvider')]
    public function testConstraintValidationOnly(string $pattern, string $input, array $expectedValues): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        $this->assertNotNull($result, "Pattern should compile and match: '$pattern'");
        
        foreach ($expectedValues as $key => $expectedValue) {
            $this->assertSame($expectedValue, $result->get($key), "Constraint validation-only - expected value for '$key'");
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
        
        // Constraints don't affect greediness - still forbidden even with constraints
        yield 'constrained-still-forbidden-max' => ['{a:int(max=999)}{b:int}'];
        yield 'constrained-still-forbidden-range' => ['{a:int(min=1, max=999)}{b:str}'];
        yield 'string-constrained-still-forbidden' => ['{name:str(maxLen=3)}{id:int}'];
        yield 'both-constrained-still-forbidden' => ['{a:int(max=999)}{b:int(max=999)}'];
        
        // Inside same SubSequence - still forbidden
        yield 'forbidden-within-subsequence' => ['PAGE({a:int}{b:int})'];
        yield 'forbidden-within-nested-subsequence' => ['PAGE{id:int}(-{a:int}{b:int})'];
        
        // Multiple adjacent groups in same sequence
        yield 'three-greedy-groups' => ['{a:int}{b:int}{c:str}'];
        yield 'four-greedy-groups' => ['{a:int}{b:str}{c:int}{d:str}'];
        
        // Remove this - {a:int}({b:str}){c:int} is actually VALID per the docs
        
        // NOTE: {a:int}?{b:int}? becomes ({a:int})({b:int}) which should be ALLOWED
        // Each ? creates its own SubSequence, so no adjacent greedy groups
        
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
        
        // NOTE: Constraints are validation-only and do NOT affect greediness.
        // All int and str types remain greedy regardless of constraints.
        
        // Single groups (always allowed)
        yield 'single-int' => ['{id:int}', '123', ['id' => 123]];
        yield 'single-string' => ['{name:str}', 'test', ['name' => 'test']];
        
        // Non-adjacent groups (literal separators)
        yield 'non-adjacent-with-literal' => ['A{a:int}B{b:int}C', 'A123B456C', ['a' => 123, 'b' => 456]];
        
        // After normalization: {a:int}? becomes ({a:int}) - SubSequence breaks adjacency
        yield 'normalized-optional-breaks-adjacency' => ['{a:int}?{b:int}', '123', ['a' => 12, 'b' => 3]];
        yield 'explicit-subsequences' => ['({a:int})({b:str})', 'test', ['a' => null, 'b' => 'test']];
        yield 'mixed-subsequence-and-required' => ['{a:int}({b:str}){c:int}', '123456', ['a' => 12345, 'b' => null, 'c' => 6]];
        yield 'mixed-with-literal-separator' => ['{a:int}-({b:str})-{c:int}', '123--456', ['a' => 123, 'b' => null, 'c' => 456]];
    }

    public static function greedyBehaviorProvider(): Generator
    {
        // All types are always greedy - constraints don't affect this
        yield 'int-always-greedy-with-separator' => [
            '{a:int}-rest', 
            '123456-rest', 
            ['a' => 123456]
        ];
        
        yield 'int-with-max-constraint-still-greedy' => [
            '{a:int(max=999999)}-rest', 
            '123456-rest', 
            ['a' => 123456]
        ];
        
        yield 'string-always-greedy-with-separator' => [
            '{name:str}-suffix', 
            'verylongname-suffix', 
            ['name' => 'verylongname']
        ];
        
        yield 'string-with-maxlen-constraint-still-greedy' => [
            '{name:str(maxLen=20)}-suffix', 
            'verylongname-suffix', 
            ['name' => 'verylongname']
        ];
        
        // Min constraints don't affect greediness either
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

    public static function constraintValidationProvider(): Generator
    {
        // Constraints are validation-only, applied AFTER regex matching
        // They do NOT affect pattern generation or greediness
        
        yield 'constraint-validation-with-separator' => [
            '{id:int(min=100, max=999)}-{name:str}', 
            '123-test', 
            ['id' => 123, 'name' => 'test']
        ];
        
        yield 'string-length-validation-with-separator' => [
            '{name:str(minLen=2, maxLen=10)}-{id:int}', 
            'hello-123', 
            ['name' => 'hello', 'id' => 123]
        ];
        
        yield 'default-constraint-validation' => [
            '{id:int(default=42)}?-{name:str}', 
            '123-test', 
            ['id' => 123, 'name' => 'test']
        ];
        
        yield 'default-applied-when-missing' => [
            '{id:int(default=42)}?-{name:str}', 
            '-test', 
            ['id' => 42, 'name' => 'test']
        ];
        
        yield 'complex-constraints-with-separators' => [
            '{id:int(min=1, max=999)}-{code:str(minLen=2, maxLen=5)}-{flag:int(max=999)}', 
            '123-ABC-456', 
            ['id' => 123, 'code' => 'ABC', 'flag' => 456]
        ];
    }
}