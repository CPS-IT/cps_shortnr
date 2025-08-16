<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Config\Ast;

use CPSIT\ShortNr\Config\Ast\PatternBuilder;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Extensive tests for pattern syntax edge cases and special character handling
 * Tests all the weird and wonderful patterns the DSL should support
 */
class PatternSyntaxExtensiveTest extends TestCase
{
    private PatternBuilder $patternBuilder;

    protected function setUp(): void
    {
        $this->patternBuilder = new PatternBuilder(new TypeRegistry());
    }

    #[DataProvider('escapedSpecialCharactersProvider')]
    public function testEscapedSpecialCharacters(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
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

    #[DataProvider('adjacentGroupsProvider')]
    public function testAdjacentGroupsWithoutSeparators(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
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

    #[DataProvider('typeCoercionGenerationProvider')]
    public function testTypeCoercionDuringGeneration(string $pattern, array $values, ?string $expectedOutput, bool $shouldThrow = false): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        if ($shouldThrow) {
            $this->expectException(\Exception::class);
            $compiled->generate($values);
        } else {
            $generated = $compiled->generate($values);
            $this->assertSame($expectedOutput, $generated);
            
            // Verify round-trip works
            $matchResult = $compiled->match($generated);
            $this->assertNotNull($matchResult, "Generated output should match back to pattern");
        }
    }

    #[DataProvider('specialCharactersInValuesProvider')]
    public function testSpecialCharactersInValues(string $pattern, array $values, string $expectedOutput): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $generated = $compiled->generate($values);
        $this->assertSame($expectedOutput, $generated);
        
        // Test round-trip
        $matchResult = $compiled->match($generated);
        $this->assertNotNull($matchResult, "Generated output with special chars should match back");
        
        foreach ($values as $key => $expectedValue) {
            $this->assertSame($expectedValue, $matchResult->get($key));
        }
    }

    #[DataProvider('extremePatternSyntaxProvider')]
    public function testExtremePatternSyntax(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Extreme pattern '$pattern' should match input '$input'");
            
            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key));
            }
        } else {
            $this->assertNull($result, "Extreme pattern '$pattern' should NOT match input '$input'");
        }
    }

    #[DataProvider('regexMetacharacterProvider')]
    public function testRegexMetacharacterEscaping(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
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

    #[DataProvider('constraintCombinationsProvider')]
    public function testConstraintCombinations(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->patternBuilder->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Pattern '$pattern' should match input '$input'");
            $this->assertEmpty($result->getErrors(), "Should not have constraint errors");
            
            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key));
            }
        } else {
            // Could be regex non-match or constraint violation
            if ($result !== null) {
                $this->assertNotEmpty($result->getErrors(), "Should have constraint violations");
            } else {
                $this->assertNull($result, "Should not match at regex level");
            }
        }
    }

    // Data Providers

    public static function escapedSpecialCharactersProvider(): Generator
    {
        // Test all special regex characters mentioned in docs
        yield 'dot-literal' => ['user.{id:int}', 'user.123', true, ['id' => 123]];
        yield 'dot-literal-fail' => ['user.{id:int}', 'userX123', false];
        
        yield 'dollar-literal' => ['price: ${amount:int}', 'price: $50', true, ['amount' => 50]];
        yield 'dollar-literal-fail' => ['price: ${amount:int}', 'price: X50', false];
        
        yield 'caret-literal' => ['^{id:int}', '^123', true, ['id' => 123]];
        yield 'caret-literal-fail' => ['^{id:int}', 'X123', false];
        
        yield 'asterisk-literal' => ['*{id:int}', '*123', true, ['id' => 123]];
        yield 'asterisk-literal-fail' => ['*{id:int}', 'X123', false];
        
        yield 'plus-literal' => ['+{id:int}', '+123', true, ['id' => 123]];
        yield 'plus-literal-fail' => ['+{id:int}', 'X123', false];
        
        yield 'question-literal' => ['?{id:int}', '?123', true, ['id' => 123]];
        yield 'question-literal-fail' => ['?{id:int}', 'X123', false];
        
        yield 'square-brackets-literal' => ['[test]{id:int}', '[test]123', true, ['id' => 123]];
        yield 'square-brackets-literal-fail' => ['[test]{id:int}', 'Xtest]123', false];
        
        yield 'backslash-literal' => ['path\\{id:int}', 'path\\123', true, ['id' => 123]];
        yield 'backslash-literal-fail' => ['path\\{id:int}', 'path/123', false];
        
        yield 'pipe-literal' => ['a|b{id:int}', 'a|b123', true, ['id' => 123]];
        yield 'pipe-literal-fail' => ['a|b{id:int}', 'a&b123', false];
    }

    public static function adjacentGroupsProvider(): Generator
    {
        // Valid adjacent groups - previous group constrained (non-greedy)
        yield 'two-ints-adjacent-constrained' => ['{a:int(max=999)}{b:int}', '123456', true, ['a' => 123, 'b' => 456]];
        yield 'int-str-adjacent-constrained' => ['{id:int(max=999)}{name:str}', '123abc', true, ['id' => 123, 'name' => 'abc']];
        yield 'str-int-adjacent-constrained' => ['{name:str(maxLen=5)}{id:int}', 'abc123', true, ['name' => 'abc', 'id' => 123]];
        
        // Adjacent with optional - still need constraints to be valid
        yield 'optional-adjacent-constrained' => ['{a:int(max=999)}{b:int}?', '123456', true, ['a' => 123, 'b' => 456]];
        yield 'optional-adjacent-minimal' => ['{a:int(max=9)}?{b:int}?', '1', true, ['a' => 1, 'b' => null]];
        
        // Multiple adjacent groups with proper constraints
        yield 'three-groups-adjacent-constrained' => ['{a:int(max=9)}{b:int(max=99)}{c:int}', '1234567', true, ['a' => 1, 'b' => 23, 'c' => 4567]];
        yield 'mixed-types-adjacent-constrained' => ['{id:int(max=999)}{code:str(maxLen=3)}{flag:int}', '123ABC456', true, ['id' => 123, 'code' => 'ABC', 'flag' => 456]];
    }

    public static function typeCoercionGenerationProvider(): Generator
    {
        // Type coercion during generation
        yield 'int-from-string' => ['PAGE{uid:int}', ['uid' => '123'], 'PAGE123'];
        yield 'int-from-numeric-string' => ['PAGE{uid:int}', ['uid' => '999'], 'PAGE999'];
        yield 'string-from-int' => ['USER{name:str}', ['name' => 123], 'USER123'];
        yield 'string-from-float' => ['USER{name:str}', ['name' => 123.45], 'USER123.45'];
        
        // Null/empty handling
        yield 'null-optional-value' => ['PAGE{uid:int}{name:str}?', ['uid' => 123, 'name' => null], 'PAGE123'];
        yield 'empty-string-value' => ['PAGE{uid:int}-{suffix:str}', ['uid' => 123, 'suffix' => ''], 'PAGE123-'];
        
        // Edge cases that might throw
        yield 'missing-required-throws' => ['PAGE{uid:int}', [], null, true];
        yield 'invalid-type-throws' => ['PAGE{uid:int}', ['uid' => [1, 2, 3]], null, true];
    }

    public static function specialCharactersInValuesProvider(): Generator
    {
        // Special characters in generated values
        yield 'hyphen-in-string' => ['USER{name:str}', ['name' => 'john-doe'], 'USERjohn-doe'];
        yield 'underscore-in-string' => ['USER{name:str}', ['name' => 'john_doe'], 'USERjohn_doe'];
        yield 'dot-in-string' => ['USER{name:str}', ['name' => 'john.doe'], 'USERjohn.doe'];
        yield 'numbers-in-string' => ['USER{name:str}', ['name' => 'user123'], 'USERuser123'];
        yield 'mixed-special-chars' => ['USER{name:str}', ['name' => 'user_123.test-name'], 'USERuser_123.test-name'];
        
        // Unicode in values
        yield 'unicode-in-string' => ['USER{name:str}', ['name' => 'jöhn'], 'USERjöhn'];
        yield 'emoji-in-string' => ['USER{name:str}', ['name' => 'user🚀'], 'USERuser🚀'];
        
        // Edge cases
        yield 'very-long-string' => ['USER{name:str}', ['name' => str_repeat('a', 100)], 'USER' . str_repeat('a', 100)];
        yield 'single-char-string' => ['USER{name:str}', ['name' => 'a'], 'USERa'];
    }

    public static function extremePatternSyntaxProvider(): Generator
    {
        // Maximum nesting levels
        yield 'deep-nesting-5-levels' => [
            'A{a:int}(B{b:int}(C{c:int}(D{d:int}(E{e:int}))))',
            'A1B2C3D4E5',
            true,
            ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]
        ];
        
        // Many optional groups in sequence - properly constrained
        yield 'many-optionals-constrained' => [
            '{a:int(max=9)}?{b:int(max=9)}?{c:int(max=9)}?{d:int(max=9)}?{e:int(max=9)}?{f:int}?',
            '123456',
            true,
            ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6]
        ];
        
        // Mixed constraints on multiple groups
        yield 'complex-multi-constraint' => [
            'ITEM{id:int(min=100, max=999)}-{code:str(minLen=3, maxLen=5)}-{version:int(min=1, default=1)}?',
            'ITEM500-ABC-2',
            true,
            ['id' => 500, 'code' => 'ABC', 'version' => 2]
        ];
        
        // Very long literal prefixes
        yield 'long-literal-prefix' => [
            'VeryLongPrefixThatTestsTheSystemsAbilityToHandleLongLiterals{id:int}',
            'VeryLongPrefixThatTestsTheSystemsAbilityToHandleLongLiterals123',
            true,
            ['id' => 123]
        ];
        
        // Multiple separators and special chars
        yield 'complex-separators' => [
            'prefix-{a:int}.suffix+{b:str}#{c:int}',
            'prefix-123.suffix+test#456',
            true,
            ['a' => 123, 'b' => 'test', 'c' => 456]
        ];
    }

    public static function regexMetacharacterProvider(): Generator
    {
        // All regex metacharacters that should be escaped
        yield 'dot-in-literal' => ['test.{id:int}', 'test.123', true, ['id' => 123]];
        yield 'dot-should-not-match-any' => ['test.{id:int}', 'testX123', false];
        
        yield 'star-in-literal' => ['test*{id:int}', 'test*123', true, ['id' => 123]];
        yield 'star-should-not-repeat' => ['test*{id:int}', 'testttt123', false];
        
        yield 'plus-in-literal' => ['test+{id:int}', 'test+123', true, ['id' => 123]];
        yield 'plus-should-not-repeat' => ['test+{id:int}', 'testttt123', false];
        
        yield 'question-in-literal' => ['test?{id:int}', 'test?123', true, ['id' => 123]];
        yield 'question-should-not-make-optional' => ['test?{id:int}', 'tes123', false];
        
        yield 'caret-in-literal' => ['^start{id:int}', '^start123', true, ['id' => 123]];
        yield 'dollar-in-literal' => ['end${id:int}', 'end$123', true, ['id' => 123]];
        
        yield 'brackets-in-literal' => ['[test]{id:int}', '[test]123', true, ['id' => 123]];
        yield 'brackets-should-not-create-class' => ['[test]{id:int}', 't123', false];
        
        yield 'parens-in-literal' => ['(test){id:int}', '(test)123', true, ['id' => 123]];
        yield 'parens-should-not-group' => ['(test){id:int}', 'test123', false];
        
        yield 'pipe-in-literal' => ['a|b{id:int}', 'a|b123', true, ['id' => 123]];
        yield 'pipe-should-not-alternate' => ['a|b{id:int}', 'a123', false];
        
        yield 'backslash-in-literal' => ['path\\file{id:int}', 'path\\file123', true, ['id' => 123]];
    }

    public static function constraintCombinationsProvider(): Generator
    {
        // Multiple constraints on single field
        yield 'int-min-max-default' => [
            'PAGE{uid:int(min=10, max=99, default=50)}?',
            'PAGE',
            true,
            ['uid' => 50]
        ];
        
        yield 'int-all-constraints-valid' => [
            'PAGE{uid:int(min=10, max=99, default=50)}?',
            'PAGE25',
            true,
            ['uid' => 25]
        ];
        
        yield 'int-violates-min' => [
            'PAGE{uid:int(min=10, max=99, default=50)}?',
            'PAGE5',
            false
        ];
        
        yield 'int-violates-max' => [
            'PAGE{uid:int(min=10, max=99, default=50)}?',
            'PAGE150',
            false
        ];
        
        // String with multiple constraints
        yield 'string-all-constraints' => [
            'USER{name:str(minLen=3, maxLen=10, startsWith=john)}',
            'USERjohnsmith',
            true,
            ['name' => 'johnsmith']
        ];
        
        yield 'string-too-short' => [
            'USER{name:str(minLen=3, maxLen=10, startsWith=john)}',
            'USERjo',
            false
        ];
        
        yield 'string-too-long' => [
            'USER{name:str(minLen=3, maxLen=10, startsWith=john)}',
            'USERjohnsmithverylong',
            false
        ];
        
        yield 'string-wrong-prefix' => [
            'USER{name:str(minLen=3, maxLen=10, startsWith=john)}',
            'USERmark',
            false
        ];
        
        // Mixed type constraints in same pattern
        yield 'mixed-types-all-valid' => [
            'ITEM{id:int(min=1, max=999)}-{code:str(minLen=2, maxLen=5)}-{version:int(default=1)}?',
            'ITEM123-ABC',
            true,
            ['id' => 123, 'code' => 'ABC', 'version' => 1]
        ];
        
        // Test adjacent groups with mixed constraints - valid under new rules
        yield 'adjacent-mixed-constraints-valid' => [
            'ITEM{id:int(max=999)}{code:str(minLen=2, maxLen=5)}',
            'ITEM123ABC',
            true,
            ['id' => 123, 'code' => 'ABC']
        ];
        
        yield 'mixed-types-int-invalid' => [
            'ITEM{id:int(min=1, max=999)}-{code:str(minLen=2, maxLen=5)}-{version:int(default=1)}?',
            'ITEM1000-ABC',
            false
        ];
        
        yield 'mixed-types-string-invalid' => [
            'ITEM{id:int(min=1, max=999)}-{code:str(minLen=2, maxLen=5)}-{version:int(default=1)}?',
            'ITEM123-A',
            false
        ];
    }
}
