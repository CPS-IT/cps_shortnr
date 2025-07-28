<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\StringContainsOperator;

/**
 * Comprehensive test suite for StringContainsOperator
 * Tests string LIKE operations with wildcard escaping
 */
final class StringContainsOperatorTest extends BaseOperatorTest
{
    private StringContainsOperator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = new StringContainsOperator();
    }

    /**
     * @dataProvider supportDataProvider
     */
    public function testSupports(mixed $fieldConfig, bool $expectedSupport, string $scenario): void
    {
        $result = $this->operator->supports($fieldConfig);
        $this->assertEquals($expectedSupport, $result, 
            "Support check failed for scenario: {$scenario}");
    }

    public static function supportDataProvider(): array
    {
        return [
            // Should support arrays with 'contains' key
            'simple contains' => [['contains' => 'test'], true, 'array with contains key'],
            'contains with string' => [['contains' => 'search term'], true, 'contains with multi-word'],
            'contains empty string' => [['contains' => ''], true, 'contains with empty string'],
            'contains with special chars' => [['contains' => '@#$%'], true, 'contains with special characters'],
            'contains with number' => [['contains' => '123'], true, 'contains with numeric string'],
            'contains with wildcards' => [['contains' => 'test%_'], true, 'contains with SQL wildcards'],
            'contains with null' => [['contains' => null], true, 'contains with null value'],
            'contains with boolean' => [['contains' => true], true, 'contains with boolean'],
            'complex array with contains' => [['contains' => 'test', 'other' => 'ignored'], true, 'complex array with contains'],
            
            // Should not support other structures
            'no contains key' => [['eq' => 'test'], false, 'array without contains key'],
            'empty array' => [[], false, 'empty array'],
            'scalar string' => ['test', false, 'scalar string'],
            'scalar integer' => [42, false, 'scalar integer'],
            'null value' => [null, false, 'null value'],
            'boolean value' => [true, false, 'boolean value'],
            'list array' => [['active', 'pending'], false, 'sequential array'],
            'other operators' => [['starts' => 'test'], false, 'different string operator'],
            'nested contains' => [['not' => ['contains' => 'test']], false, 'nested contains without direct key'],
        ];
    }

    /**
     * @dataProvider processDataProvider
     */
    public function testProcess(
        array $fieldConfig,
        bool $hasNotInHistory,
        string $expectedOperator,
        string $expectedValue,
        string $scenario
    ): void {
        $fieldName = 'test_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify SQL expression format
        $expectedPattern = "/^{$fieldName} {$expectedOperator} :dcValue\\d+$/";
        $this->assertSqlExpression($expectedPattern, $result);
        
        // Verify parameter creation with wildcards
        $expectedParameter = '%' . $expectedValue . '%';
        $this->assertParameterCreated($expectedParameter);
    }

    public static function processDataProvider(): array
    {
        $scenarios = [];
        
        // Test different string patterns with and without NOT operator
        foreach (self::stringPatternData() as $patternKey => $patternData) {
            // Without NOT operator
            $scenarios["{$patternKey} without NOT"] = [
                'fieldConfig' => ['contains' => $patternData['value']],
                'hasNotInHistory' => false,
                'expectedOperator' => 'LIKE',
                'expectedValue' => $patternData['value'],
                'scenario' => "{$patternKey} normal LIKE operation",
            ];
            
            // With NOT operator
            $scenarios["{$patternKey} with NOT"] = [
                'fieldConfig' => ['contains' => $patternData['value']],
                'hasNotInHistory' => true,
                'expectedOperator' => 'NOT LIKE',
                'expectedValue' => $patternData['value'],
                'scenario' => "{$patternKey} negated NOT LIKE operation",
            ];
        }
        
        return $scenarios;
    }

    /**
     * @dataProvider wildcardEscapingDataProvider
     */
    public function testWildcardEscaping(
        string $input,
        string $expectedEscaped,
        string $scenario
    ): void {
        $fieldName = 'escape_field';
        $fieldConfig = ['contains' => $input];
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify that the parameter includes proper escaping and wildcards
        $expectedParameter = '%' . $expectedEscaped . '%';
        $this->assertParameterCreated($expectedParameter);
    }

    public static function wildcardEscapingDataProvider(): array
    {
        return [
            // Test SQL wildcard escaping
            'percent sign' => ['test%value', 'test\\%value', 'percent sign escaping'],
            'underscore' => ['test_value', 'test\\_value', 'underscore escaping'],
            'both wildcards' => ['test%_value', 'test\\%\\_value', 'both wildcards escaping'],
            'multiple percent' => ['%test%value%', '\\%test\\%value\\%', 'multiple percent signs'],
            'multiple underscore' => ['_test_value_', '\\_test\\_value\\_', 'multiple underscores'],
            'mixed wildcards' => ['%_test_%_', '\\%\\_test\\_\\%\\_', 'mixed wildcard patterns'],
            
            // Should not escape other characters
            'backslash' => ['test\\value', 'test\\value', 'backslash not escaped'],
            'quotes' => ["test'value", "test'value", 'single quotes not escaped'],
            'double quotes' => ['test"value', 'test"value', 'double quotes not escaped'],
            'special chars' => ['test@#$value', 'test@#$value', 'other special chars not escaped'],
            
            // Edge cases
            'only wildcards' => ['%_', '\\%\\_', 'only wildcard characters'],
            'empty string' => ['', '', 'empty string no escaping needed'],
            'no wildcards' => ['normal text', 'normal text', 'normal text no escaping'],
        ];
    }

    /**
     * @dataProvider fieldNameVariationsProvider
     */
    public function testProcessWithVariousFieldNames(string $fieldName): void
    {
        $fieldConfig = ['contains' => 'test'];
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
     * @dataProvider dataTypeHandlingDataProvider
     */
    public function testDataTypeHandling(
        mixed $inputValue,
        string $expectedStringValue,
        string $scenario
    ): void {
        $fieldName = 'type_field';
        $fieldConfig = ['contains' => $inputValue];
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify that non-string values are converted to strings for LIKE operation
        $expectedParameter = '%' . $expectedStringValue . '%';
        $this->assertParameterCreated($expectedParameter);
        
        $this->assertStringContainsString('LIKE', $result, 
            "Should use LIKE operation for scenario: {$scenario}");
    }

    public static function dataTypeHandlingDataProvider(): array
    {
        return [
            // Various data types that should be converted to strings
            'integer' => [123, '123', 'integer converted to string'],
            'float' => [3.14, '3.14', 'float converted to string'],
            'boolean true' => [true, '1', 'boolean true as string'],
            'boolean false' => [false, '', 'boolean false as empty string'],
            'null' => [null, '', 'null as empty string'],
            'zero' => [0, '0', 'zero as string'],
            'negative number' => [-42, '-42', 'negative number as string'],
            
            // String values should remain unchanged
            'string number' => ['456', '456', 'string number unchanged'],
            'string boolean' => ['true', 'true', 'string boolean unchanged'],
            'string with spaces' => [' test ', ' test ', 'string with spaces unchanged'],
        ];
    }

    /**
     * @dataProvider edgeCaseDataProvider
     */
    public function testProcessEdgeCases(
        array $fieldConfig,
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
            // Extreme string lengths
            'very long string' => [
                ['contains' => str_repeat('test', 100)], 
                false, 'normal LIKE', 'very long search string'
            ],
            'very long with NOT' => [
                ['contains' => str_repeat('a', 500)], 
                true, 'negated NOT LIKE', 'very long string with negation'
            ],
            
            // Unicode and special characters
            'unicode string' => [
                ['contains' => 'tëst ñañé'], 
                false, 'normal LIKE', 'unicode characters'
            ],
            'emoji string' => [
                ['contains' => '🚀 test 🎉'], 
                true, 'negated NOT LIKE', 'emoji characters with negation'
            ],
            
            // SQL injection attempts (should be safely escaped)
            'sql injection attempt' => [
                ['contains' => "'; DROP TABLE users; --"], 
                false, 'normal LIKE', 'potential SQL injection'
            ],
            'complex injection' => [
                ['contains' => "test' OR '1'='1"], 
                true, 'negated NOT LIKE', 'complex injection attempt'
            ],
            
            // Whitespace variations
            'only spaces' => [
                ['contains' => '   '], 
                false, 'normal LIKE', 'only whitespace'
            ],
            'tabs and newlines' => [
                ['contains' => "\t\n\r"], 
                true, 'negated NOT LIKE', 'whitespace characters'
            ],
        ];
    }

    /**
     * Test behavior with null history
     */
    public function testProcessWithNullHistory(): void
    {
        $fieldName = 'null_history_field';
        $fieldConfig = ['contains' => 'test'];
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, null);
        
        // Should behave as normal LIKE when no history
        $this->assertStringContainsString('LIKE', $result);
        $this->assertStringNotContainsString('NOT LIKE', $result);
    }

    /**
     * Test that wildcard escaping is properly integrated
     */
    public function testWildcardEscapingIntegration(): void
    {
        $fieldName = 'escape_integration_field';
        $searchValue = 'test%_value';
        $fieldConfig = ['contains' => $searchValue];
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify that both wildcard escaping and percentage wrapping occurred
        $expectedParameter = '%test\\%\\_value%';
        $this->assertParameterCreated($expectedParameter);
    }

    /**
     * Test complex scenarios combining NOT operator with wildcard escaping
     */
    public function testComplexNotWithWildcards(): void
    {
        $fieldName = 'complex_field';
        $searchValue = 'complex%_test';
        $fieldConfig = ['contains' => $searchValue];
        $history = $this->createOperatorHistory(true); // Has NOT in history
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Should use NOT LIKE
        $this->assertStringContainsString('NOT LIKE', $result);
        $this->assertStringNotContainsString(' LIKE ', $result); // Make sure it's NOT LIKE, not just LIKE
        
        // Should still escape wildcards
        $expectedParameter = '%complex\\%\\_test%';
        $this->assertParameterCreated($expectedParameter);
    }
}