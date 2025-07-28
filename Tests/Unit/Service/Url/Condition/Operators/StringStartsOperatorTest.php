<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\StringStartsOperator;

/**
 * Comprehensive test suite for StringStartsOperator
 * Tests string prefix matching with LIKE operator
 * 
 * Based on config syntax: version: { starts: 'v' }
 */
final class StringStartsOperatorTest extends BaseOperatorTest
{
    private StringStartsOperator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = new StringStartsOperator();
    }

    /**
     * @dataProvider supportDataProvider
     */
    public function testSupports(mixed $fieldConfig, bool $expectedSupport, string $scenario): void
    {
        $result = $this->operator->supports($fieldConfig);
        $this->assertEquals($expectedSupport, $result, "Support check failed for scenario: {$scenario}");
    }

    public static function supportDataProvider(): array
    {
        return [
            // Arrays with 'starts' key (should be supported)
            'starts with simple string' => [['starts' => 'v'], true, 'simple prefix'],
            'starts with complex string' => [['starts' => 'version_'], true, 'complex prefix'],
            'starts with empty string' => [['starts' => ''], true, 'empty prefix'],
            'starts with special chars' => [['starts' => '@#$'], true, 'special characters'],
            'starts with number string' => [['starts' => '123'], true, 'numeric prefix'],
            'starts with whitespace' => [['starts' => ' test'], true, 'whitespace prefix'],
            'starts with unicode' => [['starts' => 'café'], true, 'unicode characters'],
            'complex array with starts' => [['starts' => 'prefix', 'other' => 'ignored'], true, 'complex array with starts'],
            
            // Arrays without 'starts' key (should not be supported)
            'empty array' => [[], false, 'empty array'],
            'array without starts' => [['contains' => 'test'], false, 'array with other operators'],
            'list array' => [['active', 'pending'], false, 'sequential array'],
            'complex array without starts' => [['not' => ['eq' => 'test']], false, 'nested without starts'],
            
            // Scalar values (should not be supported - starts requires array syntax)
            'scalar string' => ['test', false, 'scalar string'],
            'scalar integer' => [42, false, 'scalar integer'],
            'scalar null' => [null, false, 'scalar null'],
            'scalar boolean' => [true, false, 'scalar boolean'],
        ];
    }

    /**
     * @dataProvider processDataProvider
     */
    public function testProcess(
        mixed $fieldConfig,
        bool $hasNotInHistory,
        string $expectedOperator,
        string $expectedPattern,
        string $scenario
    ): void {
        $fieldName = 'test_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify SQL expression format
        $expectedRegex = "/^{$fieldName} {$expectedOperator} :dcValue\\d+$/";
        $this->assertSqlExpression($expectedRegex, $result);
        
        // Verify parameter creation with proper pattern
        $this->assertParameterCreated($expectedPattern);
    }

    public static function processDataProvider(): array
    {
        $scenarios = [];
        
        // Test various string patterns with and without NOT operator
        $stringPrefixes = [
            'simple prefix' => 'v',
            'word prefix' => 'admin',
            'version prefix' => 'version_',
            'number prefix' => '123',
            'special chars' => '@#$%',
            'with spaces' => ' test',
            'empty string' => '',
            'single char' => 'a',
            'unicode chars' => 'café',
            'with wildcards' => 'test%_',
            'mixed case' => 'Version',
            'long prefix' => 'very_long_prefix_string',
        ];
        
        foreach ($stringPrefixes as $prefixKey => $prefix) {
            $escapedPrefix = addcslashes($prefix, '%_'); // Simulates escapeLikeWildcards
            
            // Without NOT operator - uses LIKE with % suffix
            $scenarios["{$prefixKey} without NOT"] = [
                'fieldConfig' => ['starts' => $prefix],
                'hasNotInHistory' => false,
                'expectedOperator' => 'LIKE',
                'expectedPattern' => $escapedPrefix . '%',
                'scenario' => "{$prefixKey} normal starts with",
            ];
            
            // With NOT operator - uses NOT LIKE with % suffix
            $scenarios["{$prefixKey} with NOT"] = [
                'fieldConfig' => ['starts' => $prefix],
                'hasNotInHistory' => true,
                'expectedOperator' => 'NOT LIKE',
                'expectedPattern' => $escapedPrefix . '%',
                'scenario' => "{$prefixKey} negated starts with",
            ];
        }
        
        return $scenarios;
    }

    /**
     * @dataProvider fieldNameVariationsProvider
     */
    public function testProcessWithVariousFieldNames(string $fieldName): void
    {
        $fieldConfig = ['starts' => 'test'];
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify field name is preserved in expression
        $this->assertStringContainsString($fieldName, $result, 
            "Field name '{$fieldName}' not found in expression");
        $this->assertStringContainsString('LIKE', $result, 
            "LIKE operator not found in expression");
    }

    public static function fieldNameVariationsProvider(): array
    {
        return self::fieldNameProvider();
    }

    /**
     * @dataProvider edgeCaseDataProvider
     */
    public function testProcessEdgeCases(
        mixed $fieldConfig,
        bool $hasNotInHistory,
        string $expectedBehavior,
        string $scenario
    ): void {
        $fieldName = 'edge_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertIsString($result, "Process should return string expression");
        $this->assertNotEmpty($result, "Expression should not be empty");
        
        // Verify operator type based on NOT history
        $expectedOp = $hasNotInHistory ? 'NOT LIKE' : 'LIKE';
        $this->assertStringContainsString($expectedOp, $result, 
            "Expected operator '{$expectedOp}' not found for scenario: {$scenario}");
    }

    public static function edgeCaseDataProvider(): array
    {
        return [
            // Special string patterns
            'empty prefix without NOT' => [['starts' => ''], false, 'matches all strings', 'empty prefix'],
            'empty prefix with NOT' => [['starts' => ''], true, 'matches no strings', 'empty prefix negated'],
            'single char prefix' => [['starts' => 'a'], false, 'matches a%', 'minimal prefix'],
            'whitespace prefix' => [['starts' => ' '], false, 'matches space prefix', 'space character'],
            
            // Special characters that need escaping
            'wildcard percent' => [['starts' => 'test%'], false, 'escapes percent', 'contains SQL wildcard %'],
            'wildcard underscore' => [['starts' => 'test_'], false, 'escapes underscore', 'contains SQL wildcard _'],
            'both wildcards' => [['starts' => 'test%_'], false, 'escapes both wildcards', 'contains both SQL wildcards'],
            'mixed wildcards' => [['starts' => '%_test'], false, 'escapes at start', 'wildcards at beginning'],
            
            // Unicode and international characters
            'unicode prefix' => [['starts' => 'café'], false, 'matches unicode', 'unicode characters'],
            'emoji prefix' => [['starts' => '🚀'], false, 'matches emoji', 'emoji character'],
            'mixed unicode' => [['starts' => 'test_café'], false, 'matches mixed unicode', 'mixed characters'],
            
            // Complex scenarios
            'long prefix' => [
                ['starts' => 'very_long_prefix_string_that_should_work'], 
                false, 
                'matches long prefix', 
                'very long prefix string'
            ],
            'special chars prefix' => [
                ['starts' => '@#$%^&*()'], 
                false, 
                'matches special chars', 
                'special characters prefix'
            ],
            
            // Multi-key array
            'multi-key array' => [
                ['starts' => 'prefix', 'ignored' => 'value'], 
                false, 
                'uses starts value only', 
                'multi-key array'
            ],
        ];
    }

    /**
     * Test wildcard escaping functionality
     * 
     * @dataProvider wildcardEscapingProvider
     */
    public function testWildcardEscaping(
        string $inputPrefix,
        string $expectedEscapedPattern,
        string $scenario
    ): void {
        $fieldName = 'wildcard_field';
        $fieldConfig = ['starts' => $inputPrefix];
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify parameter was created with properly escaped pattern
        $this->assertParameterCreated($expectedEscapedPattern);
    }

    public static function wildcardEscapingProvider(): array
    {
        return [
            'no wildcards' => ['test', 'test%', 'normal string'],
            'with percent' => ['test%', 'test\\%%', 'contains percent wildcard'],
            'with underscore' => ['test_', 'test\\_%', 'contains underscore wildcard'],
            'both wildcards' => ['test%_', 'test\\%\\_%', 'contains both wildcards'],
            'wildcards at start' => ['%_test', '\\%\\_test%', 'wildcards at beginning'],
            'wildcards in middle' => ['te%st_', 'te\\%st\\_%', 'wildcards in middle'],
            'multiple percents' => ['test%value%', 'test\\%value\\%%', 'multiple percent signs'],
            'multiple underscores' => ['test_value_', 'test\\_value\\_%', 'multiple underscores'],
            'complex pattern' => ['%test_value%end', '\\%test\\_value\\%end%', 'complex wildcard pattern'],
        ];
    }

    /**
     * Test behavior with null history
     */
    public function testProcessWithNullHistory(): void
    {
        $fieldName = 'null_history_field';
        $fieldConfig = ['starts' => 'test'];
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, null);
        
        // Should behave as normal LIKE when no history
        $this->assertStringContainsString('LIKE', $result);
        $this->assertStringNotContainsString('NOT LIKE', $result);
    }

    /**
     * Test that operator correctly extracts prefix from array config
     */
    public function testArrayConfigValueExtraction(): void
    {
        $fieldName = 'extraction_field';
        $testPrefix = 'extracted_prefix';
        $fieldConfig = ['starts' => $testPrefix, 'other_key' => 'should_be_ignored'];
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify that only the 'starts' value was used for parameter (with % suffix and escaping)
        $expectedEscapedValue = addcslashes($testPrefix, '%_') . '%';
        $this->assertParameterCreated($expectedEscapedValue);
    }

    /**
     * Test pattern generation consistency
     * 
     * @dataProvider patternGenerationProvider
     */
    public function testPatternGeneration(
        string $prefix,
        bool $hasNot,
        string $expectedSqlOp,
        string $expectedPattern,
        string $scenario
    ): void {
        $fieldName = 'pattern_field';
        $fieldConfig = ['starts' => $prefix];
        $history = $this->createOperatorHistory($hasNot);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertStringContainsString($expectedSqlOp, $result, 
            "SQL operator incorrect for scenario: {$scenario}");
        $this->assertParameterCreated($expectedPattern);
    }

    public static function patternGenerationProvider(): array
    {
        return [
            // Basic patterns
            'simple normal' => ['test', false, 'LIKE', 'test%', 'simple prefix normal'],
            'simple negated' => ['test', true, 'NOT LIKE', 'test%', 'simple prefix negated'],
            
            // Empty patterns
            'empty normal' => ['', false, 'LIKE', '%', 'empty prefix normal'],
            'empty negated' => ['', true, 'NOT LIKE', '%', 'empty prefix negated'],
            
            // Special character patterns
            'wildcard normal' => ['test%', false, 'LIKE', 'test\\%%', 'wildcard prefix normal'],
            'wildcard negated' => ['test%', true, 'NOT LIKE', 'test\\%%', 'wildcard prefix negated'],
            'underscore normal' => ['test_', false, 'LIKE', 'test\\_%', 'underscore prefix normal'],
            'underscore negated' => ['test_', true, 'NOT LIKE', 'test\\_%', 'underscore prefix negated'],
            
            // Complex patterns
            'complex normal' => ['pre%fix_', false, 'LIKE', 'pre\\%fix\\_%', 'complex prefix normal'],
            'complex negated' => ['pre%fix_', true, 'NOT LIKE', 'pre\\%fix\\_%', 'complex prefix negated'],
        ];
    }

    /**
     * Test integration with QueryBuilder escape functionality
     */
    public function testQueryBuilderEscapeIntegration(): void
    {
        $fieldName = 'escape_field';
        $prefixWithWildcards = 'test%_value';
        $fieldConfig = ['starts' => $prefixWithWildcards];
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify that escapeLikeWildcards was called and result includes escaped pattern
        $expectedEscapedPattern = 'test\\%\\_value%'; // QueryBuilderMockHelper simulates escapeLikeWildcards
        $this->assertParameterCreated($expectedEscapedPattern);
    }

    /**
     * Test comprehensive string prefix scenarios from config
     * 
     * @dataProvider configScenarioProvider
     */
    public function testConfigScenarios(
        array $fieldConfig,
        bool $hasNot,
        string $expectedOperator,
        string $expectedPattern,
        string $scenario
    ): void {
        $fieldName = 'config_field';
        $history = $this->createOperatorHistory($hasNot);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertStringContainsString($expectedOperator, $result, 
            "Config scenario failed for {$scenario}");
        $this->assertParameterCreated($expectedPattern);
    }

    public static function configScenarioProvider(): array
    {
        return [
            // Based on actual config examples
            'version starts v' => [
                ['starts' => 'v'],
                false,
                'LIKE',
                'v%',
                'version field starts with v'
            ],
            'version not starts v' => [
                ['starts' => 'v'],
                true,
                'NOT LIKE',
                'v%',
                'version field not starts with v'
            ],
            'admin prefix' => [
                ['starts' => 'admin_'],
                false,
                'LIKE',
                'admin\\_%', // Underscore gets escaped
                'admin prefix pattern'
            ],
            'numeric prefix' => [
                ['starts' => '2024'],
                false,
                'LIKE',
                '2024%',
                'year prefix pattern'
            ],
            'complex business prefix' => [
                ['starts' => 'user_profile_'],
                false,
                'LIKE',
                'user\\_profile\\_%', // Both underscores escaped
                'business logic prefix'
            ],
        ];
    }
}