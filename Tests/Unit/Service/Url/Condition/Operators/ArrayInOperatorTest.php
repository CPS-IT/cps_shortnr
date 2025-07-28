<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\ArrayInOperator;
use Doctrine\DBAL\ArrayParameterType;

/**
 * Comprehensive test suite for ArrayInOperator
 * Tests array membership operations with various data types
 */
final class ArrayInOperatorTest extends BaseOperatorTest
{
    private ArrayInOperator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = new ArrayInOperator();
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
            // Sequential arrays (should be supported)
            'integer list' => [[1, 2, 3], true, 'sequential integer array'],
            'string list' => [['active', 'pending'], true, 'sequential string array'],
            'mixed list' => [[1, 'two', 3], true, 'mixed type sequential array'],
            'single item list' => [['single'], true, 'single item array'],
            'empty list' => [[], true, 'empty sequential array'],
            
            // Non-sequential arrays (should not be supported)
            'associative array' => [['key' => 'value'], false, 'associative array'],
            'eq operator array' => [['eq' => 'test'], false, 'equality operator array'],
            'contains operator array' => [['contains' => 'test'], false, 'contains operator array'],
            'complex nested' => [['not' => ['eq' => 'test']], false, 'complex nested structure'],
            
            // Non-array values (should not be supported)
            'scalar string' => ['test', false, 'scalar string'],
            'scalar integer' => [42, false, 'scalar integer'],
            'null value' => [null, false, 'null value'],
            'boolean value' => [true, false, 'boolean value'],
        ];
    }

    /**
     * @dataProvider processDataProvider
     */
    public function testProcess(
        array $fieldConfig,
        bool $hasNotInHistory,
        string $expectedOperator,
        int $expectedArrayType,
        string $scenario
    ): void {
        $fieldName = 'test_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify SQL expression format
        $expectedPattern = "/^{$fieldName} {$expectedOperator} \\(:dcValue\\d+\\)$/";
        $this->assertSqlExpression($expectedPattern, $result);
        
        // Verify parameter creation with correct array type
        $this->assertParameterCreated($fieldConfig, $expectedArrayType);
    }

    public static function processDataProvider(): array
    {
        $scenarios = [];
        
        // Test different array types with and without NOT operator
        foreach (self::arrayDataTypes() as $typeKey => $typeData) {
            // Without NOT operator
            $scenarios["{$typeKey} without NOT"] = [
                'fieldConfig' => $typeData['value'],
                'hasNotInHistory' => false,
                'expectedOperator' => 'IN',
                'expectedArrayType' => $typeData['type'],
                'scenario' => "{$typeKey} normal IN operation",
            ];
            
            // With NOT operator
            $scenarios["{$typeKey} with NOT"] = [
                'fieldConfig' => $typeData['value'],
                'hasNotInHistory' => true,
                'expectedOperator' => 'NOT IN',
                'expectedArrayType' => $typeData['type'],
                'scenario' => "{$typeKey} negated NOT IN operation",
            ];
        }
        
        return $scenarios;
    }

    /**
     * @dataProvider arrayTypeDetectionDataProvider
     */
    public function testArrayTypeDetection(
        array $values,
        int $expectedType,
        string $typeDescription
    ): void {
        $fieldName = 'type_detection_field';
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $values, $this->queryBuilder, $history);
        
        $this->assertParameterCreated($values, $expectedType);
    }

    public static function arrayTypeDetectionDataProvider(): array
    {
        return [
            // Homogeneous integer arrays
            'all integers' => [
                [1, 2, 3, 4],
                ArrayParameterType::INTEGER,
                'homogeneous integer array'
            ],
            'single integer' => [
                [42],
                ArrayParameterType::INTEGER,
                'single integer array'
            ],
            'negative integers' => [
                [-1, -2, -3],
                ArrayParameterType::INTEGER,
                'negative integer array'
            ],
            'zero included' => [
                [0, 1, 2],
                ArrayParameterType::INTEGER,
                'integers including zero'
            ],
            
            // Homogeneous string arrays
            'all strings' => [
                ['active', 'pending', 'draft'],
                ArrayParameterType::STRING,
                'homogeneous string array'
            ],
            'single string' => [
                ['single'],
                ArrayParameterType::STRING,
                'single string array'
            ],
            'empty strings' => [
                ['', 'test', ''],
                ArrayParameterType::STRING,
                'strings including empty'
            ],
            'numeric strings' => [
                ['1', '2', '3'],
                ArrayParameterType::STRING,
                'numeric string array'
            ],
            
            // Mixed type arrays (should default to STRING)
            'mixed int and string' => [
                [1, 'two', 3],
                ArrayParameterType::STRING,
                'mixed integer and string'
            ],
            'mixed with boolean' => [
                ['test', true, 42],
                ArrayParameterType::STRING,
                'mixed with boolean'
            ],
            'mixed with null' => [
                [1, null, 'test'],
                ArrayParameterType::STRING,
                'mixed with null value'
            ],
            
            // Edge cases
            'empty array' => [
                [],
                ArrayParameterType::STRING,
                'empty array defaults to string'
            ],
        ];
    }

    /**
     * @dataProvider fieldNameVariationsProvider
     */
    public function testProcessWithVariousFieldNames(string $fieldName): void
    {
        $fieldConfig = ['active', 'pending'];
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify field name is preserved in expression
        $this->assertStringContainsString($fieldName, $result, 
            "Field name '{$fieldName}' not found in expression");
        $this->assertStringContainsString('IN', $result, 
            "IN operator not found in expression");
    }

    public static function fieldNameVariationsProvider(): array
    {
        return self::fieldNameProvider();
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
        $expectedOp = $hasNotInHistory ? 'NOT IN' : 'IN';
        $this->assertStringContainsString($expectedOp, $result, 
            "Expected operator '{$expectedOp}' not found for scenario: {$scenario}");
    }

    public static function edgeCaseDataProvider(): array
    {
        return [
            // Special array contents
            'array with zeros' => [[0, 1, 2], false, 'normal IN', 'array containing zero'],
            'array with zeros negated' => [[0, 1, 2], true, 'negated NOT IN', 'array with zero negated'],
            'array with nulls' => [[null, 'test'], false, 'normal IN', 'array containing null'],
            'array with booleans' => [[true, false], true, 'negated NOT IN', 'boolean array negated'],
            
            // Large arrays
            'large integer array' => [
                range(1, 100), false, 'normal IN', 'large integer array'
            ],
            'large string array' => [
                array_map(fn($i) => "item_{$i}", range(1, 50)), 
                true, 'negated NOT IN', 'large string array negated'
            ],
            
            // Duplicate values
            'array with duplicates' => [
                ['test', 'test', 'other'], false, 'normal IN', 'array with duplicate values'
            ],
            
            // Single item arrays
            'single null' => [[null], false, 'normal IN', 'single null item'],
            'single empty string' => [[''], true, 'negated NOT IN', 'single empty string'],
        ];
    }

    /**
     * Test behavior with null history
     */
    public function testProcessWithNullHistory(): void
    {
        $fieldName = 'null_history_field';
        $fieldConfig = ['active', 'pending'];
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, null);
        
        // Should behave as normal IN when no history
        $this->assertStringContainsString('IN', $result);
        $this->assertStringNotContainsString('NOT IN', $result);
    }

    /**
     * Test that array parameter type detection works correctly for edge cases
     */
    public function testComplexArrayTypeDetection(): void
    {
        $fieldName = 'complex_field';
        $history = $this->createOperatorHistory(false);
        
        // Test array with all same integer type
        $this->operator->process($fieldName, [1, 2, 3], $this->queryBuilder, $history);
        $this->assertParameterCreated([1, 2, 3], ArrayParameterType::INTEGER);
        
        // Reset helper for next test
        $this->queryBuilderHelper->reset();
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
        
        // Test mixed array defaults to string
        $this->operator->process($fieldName, [1, 'test'], $this->queryBuilder, $history);
        $this->assertParameterCreated([1, 'test'], ArrayParameterType::STRING);
    }

    /**
     * Test extreme array sizes and contents
     */
    public function testExtremeArrayCases(): void
    {
        $fieldName = 'extreme_field';
        $history = $this->createOperatorHistory(false);
        
        // Very large array
        $largeArray = range(1, 1000);
        $result = $this->operator->process($fieldName, $largeArray, $this->queryBuilder, $history);
        $this->assertStringContainsString('IN', $result);
        $this->assertParameterCreated($largeArray, ArrayParameterType::INTEGER);
    }
}