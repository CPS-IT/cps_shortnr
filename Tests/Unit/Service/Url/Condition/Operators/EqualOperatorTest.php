<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\EqualOperator;
use TYPO3\CMS\Core\Database\Connection;

/**
 * Comprehensive test suite for EqualOperator
 * Demonstrates testing patterns for all operator combinations
 */
final class EqualOperatorTest extends BaseOperatorTest
{
    private EqualOperator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = new EqualOperator();
    }

    /**
     * @dataProvider supportDataProvider
     */
    public function testSupport(mixed $fieldConfig, bool $expectedSupport, string $scenario): void
    {
        $result = $this->operator->support($fieldConfig);
        $this->assertEquals($expectedSupport, $result, "Support check failed for scenario: {$scenario}");
    }

    public static function supportDataProvider(): array
    {
        return [
            // Scalar values (should be supported)
            'integer value' => [42, true, 'scalar integer'],
            'string value' => ['test', true, 'scalar string'],
            'boolean true' => [true, true, 'boolean true'],
            'boolean false' => [false, true, 'boolean false'],
            'null value' => [null, true, 'null value'],
            'float value' => [3.14, true, 'float value'],
            
            // Arrays with 'eq' key (should be supported)
            'array with eq key' => [['eq' => 'test'], true, 'array with eq key'],
            'complex array with eq' => [['eq' => 42, 'other' => 'ignored'], true, 'complex array with eq'],
            'nested eq' => [['eq' => ['nested' => 'value']], true, 'nested value in eq'],
            
            // Arrays without 'eq' key (should not be supported)
            'empty array' => [[], false, 'empty array'],
            'array without eq' => [['contains' => 'test'], false, 'array with other operators'],
            'list array' => [['active', 'pending'], false, 'sequential array'],
            'complex array' => [['not' => ['eq' => 'test']], false, 'nested without direct eq'],
        ];
    }

    /**
     * @dataProvider processDataProvider
     */
    public function testProcess(
        mixed $fieldConfig,
        bool $hasNotInHistory,
        string $expectedOperator,
        mixed $expectedValue,
        int $expectedType,
        string $scenario
    ): void {
        $fieldName = 'test_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify SQL expression format
        $expectedPattern = "/^{$fieldName} {$expectedOperator} :dcValue\d+$/";
        $this->assertSqlExpression($expectedPattern, $result);
        
        // Verify parameter creation
        $this->assertParameterCreated($expectedValue, $expectedType);
    }

    public static function processDataProvider(): array
    {
        $scenarios = [];
        
        // Test all data types with and without NOT operator
        foreach (self::commonDataTypes() as $typeKey => $typeData) {
            // Without NOT operator
            $scenarios["scalar {$typeKey} without NOT"] = [
                'fieldConfig' => $typeData['value'],
                'hasNotInHistory' => false,
                'expectedOperator' => '=',
                'expectedValue' => $typeData['value'],
                'expectedType' => $typeData['type'],
                'scenario' => "scalar {$typeKey} normal equality",
            ];
            
            // With NOT operator
            $scenarios["scalar {$typeKey} with NOT"] = [
                'fieldConfig' => $typeData['value'],
                'hasNotInHistory' => true,
                'expectedOperator' => '!=',
                'expectedValue' => $typeData['value'],
                'expectedType' => $typeData['type'],
                'scenario' => "scalar {$typeKey} negated equality",
            ];
            
            // Array format without NOT
            $scenarios["array {$typeKey} without NOT"] = [
                'fieldConfig' => ['eq' => $typeData['value']],
                'hasNotInHistory' => false,
                'expectedOperator' => '=',
                'expectedValue' => $typeData['value'],
                'expectedType' => $typeData['type'],
                'scenario' => "array {$typeKey} normal equality",
            ];
            
            // Array format with NOT
            $scenarios["array {$typeKey} with NOT"] = [
                'fieldConfig' => ['eq' => $typeData['value']],
                'hasNotInHistory' => true,
                'expectedOperator' => '!=',
                'expectedValue' => $typeData['value'],
                'expectedType' => $typeData['type'],
                'scenario' => "array {$typeKey} negated equality",
            ];
        }
        
        return $scenarios;
    }

    /**
     * @dataProvider fieldNameVariationsProvider
     */
    public function testProcessWithVariousFieldNames(string $fieldName): void
    {
        $fieldConfig = 'test_value';
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify field name is preserved in expression
        $this->assertStringContainsString($fieldName, $result, 
            "Field name '{$fieldName}' not found in expression");
        $this->assertStringContainsString('=', $result, 
            "Equality operator not found in expression");
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
        $expectedOp = $hasNotInHistory ? '!=' : '=';
        $this->assertStringContainsString($expectedOp, $result, 
            "Expected operator '{$expectedOp}' not found for scenario: {$scenario}");
    }

    public static function edgeCaseDataProvider(): array
    {
        return [
            // Special values
            'zero without NOT' => [0, false, 'normal equality', 'zero value'],
            'zero with NOT' => [0, true, 'negated equality', 'zero value negated'],
            'empty string without NOT' => ['', false, 'normal equality', 'empty string'],
            'empty string with NOT' => ['', true, 'negated equality', 'empty string negated'],
            'whitespace string' => ['   ', false, 'normal equality', 'whitespace string'],
            
            // Array formats
            'eq with null' => [['eq' => null], false, 'normal equality', 'null in array'],
            'eq with boolean' => [['eq' => false], true, 'negated equality', 'boolean in array'],
            'eq with nested array' => [['eq' => ['nested' => 'data']], false, 'normal equality', 'nested data'],
            
            // Complex scenarios
            'multi-key array' => [['eq' => 'test', 'ignored' => 'value'], false, 'normal equality', 'multi-key array'],
        ];
    }

    /**
     * Test parameter type determination for different value types
     * 
     * @dataProvider parameterTypeDataProvider
     */
    public function testParameterTypeDetection(mixed $value, int $expectedType, string $typeDescription): void
    {
        $fieldName = 'type_test_field';
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $value, $this->queryBuilder, $history);
        
        $this->assertParameterCreated($value, $expectedType);
    }

    public static function parameterTypeDataProvider(): array
    {
        return [
            'integer type' => [42, Connection::PARAM_INT, 'integer value'],
            'string type' => ['test', Connection::PARAM_INT_ARRAY, 'string value'], // Note: EqualOperator uses PARAM_INT_ARRAY as default
            'boolean type' => [true, Connection::PARAM_BOOL, 'boolean value'],
            'null type' => [null, Connection::PARAM_NULL, 'null value'],
            'float as default' => [3.14, Connection::PARAM_INT_ARRAY, 'float defaults to INT_ARRAY'],
        ];
    }

    /**
     * Test behavior with null history
     */
    public function testProcessWithNullHistory(): void
    {
        $fieldName = 'null_history_field';
        $fieldConfig = 'test_value';
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, null);
        
        // Should behave as normal equality when no history
        $this->assertStringContainsString('=', $result);
        $this->assertStringNotContainsString('!=', $result);
    }

    /**
     * Test that operator correctly extracts value from array config
     */
    public function testArrayConfigValueExtraction(): void
    {
        $fieldName = 'extraction_field';
        $testValue = 'extracted_value';
        $fieldConfig = ['eq' => $testValue, 'other_key' => 'should_be_ignored'];
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify that only the 'eq' value was used for parameter
        $this->assertParameterCreated($testValue);
    }
}