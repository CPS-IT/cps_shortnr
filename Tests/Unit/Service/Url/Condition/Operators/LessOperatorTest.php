<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\LessOperator;
use TYPO3\CMS\Core\Database\Connection;

/**
 * Comprehensive test suite for LessOperator
 * Tests less than (lt) and less than or equal (lte) operations
 * 
 * Based on config syntax: ranking: { lt: 30 }, age: { lte: 65 }
 */
final class LessOperatorTest extends BaseOperatorTest
{
    private LessOperator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = new LessOperator();
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
            // Arrays with 'lt' key (should be supported)
            'lt with integer' => [['lt' => 30], true, 'lt with integer value'],
            'lt with string number' => [['lt' => '42'], true, 'lt with string number'],
            'lt with float' => [['lt' => 3.14], true, 'lt with float value'],
            'lt with zero' => [['lt' => 0], true, 'lt with zero'],
            'lt with negative' => [['lt' => -10], true, 'lt with negative number'],
            'complex array with lt' => [['lt' => 100, 'other' => 'ignored'], true, 'complex array with lt'],
            
            // Arrays with 'lte' key (should be supported)
            'lte with integer' => [['lte' => 65], true, 'lte with integer value'],
            'lte with string number' => [['lte' => '50'], true, 'lte with string number'],
            'lte with float' => [['lte' => 2.71], true, 'lte with float value'],
            'lte with zero' => [['lte' => 0], true, 'lte with zero'],
            'lte with negative' => [['lte' => -5], true, 'lte with negative number'],
            'complex array with lte' => [['lte' => 80, 'other' => 'ignored'], true, 'complex array with lte'],
            
            // Arrays with both keys (lt takes precedence in current implementation)
            'both lt and lte' => [['lt' => 30, 'lte' => 40], true, 'array with both lt and lte'],
            
            // Arrays without supported keys (should not be supported)
            'empty array' => [[], false, 'empty array'],
            'array without lt/lte' => [['gt' => 30], false, 'array with other operators'],
            'list array' => [['active', 'pending'], false, 'sequential array'],
            'complex array without lt/lte' => [['not' => ['eq' => 'test']], false, 'nested without lt/lte'],
            
            // Scalar values (should not be supported - lt/lte require array syntax)
            'scalar integer' => [42, false, 'scalar integer'],
            'scalar string' => ['30', false, 'scalar string number'],
            'scalar float' => [3.14, false, 'scalar float'],
            'scalar null' => [null, false, 'scalar null'],
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
        $expectedPattern = "/^{$fieldName} {$expectedOperator} :dcValue\\d+$/";
        $this->assertSqlExpression($expectedPattern, $result);
        
        // Verify parameter creation
        $this->assertParameterCreated($expectedValue, $expectedType);
    }

    public static function processDataProvider(): array
    {
        $scenarios = [];
        
        // Test different operators (lt and lte) with various data types
        foreach (self::numericComparisonData() as $typeKey => $typeData) {
            // LT operator without NOT
            $scenarios["lt {$typeKey} without NOT"] = [
                'fieldConfig' => ['lt' => $typeData['value']],
                'hasNotInHistory' => false,
                'expectedOperator' => '<',
                'expectedValue' => $typeData['value'],
                'expectedType' => $typeData['type'],
                'scenario' => "lt {$typeKey} normal comparison",
            ];
            
            // LT operator with NOT (becomes >=)
            $scenarios["lt {$typeKey} with NOT"] = [
                'fieldConfig' => ['lt' => $typeData['value']],
                'hasNotInHistory' => true,
                'expectedOperator' => '>=',
                'expectedValue' => $typeData['value'],
                'expectedType' => $typeData['type'],
                'scenario' => "lt {$typeKey} negated comparison",
            ];
            
            // LTE operator without NOT
            $scenarios["lte {$typeKey} without NOT"] = [
                'fieldConfig' => ['lte' => $typeData['value']],
                'hasNotInHistory' => false,
                'expectedOperator' => '<=',
                'expectedValue' => $typeData['value'],
                'expectedType' => $typeData['type'],
                'scenario' => "lte {$typeKey} normal comparison",
            ];
            
            // LTE operator with NOT (becomes >)
            $scenarios["lte {$typeKey} with NOT"] = [
                'fieldConfig' => ['lte' => $typeData['value']],
                'hasNotInHistory' => true,
                'expectedOperator' => '>',
                'expectedValue' => $typeData['value'],
                'expectedType' => $typeData['type'],
                'scenario' => "lte {$typeKey} negated comparison",
            ];
        }
        
        return $scenarios;
    }

    /**
     * @dataProvider fieldNameVariationsProvider
     */
    public function testProcessWithVariousFieldNames(string $fieldName): void
    {
        $fieldConfig = ['lt' => 30];
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify field name is preserved in expression
        $this->assertStringContainsString($fieldName, $result, 
            "Field name '{$fieldName}' not found in expression");
        $this->assertStringContainsString('<', $result, 
            "Less than operator not found in expression");
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
        
        // Verify field name is present
        $this->assertStringContainsString($fieldName, $result, 
            "Field name should be in expression for scenario: {$scenario}");
        
        // Verify comparison operator is present
        $this->assertMatchesRegularExpression('/[<>=]+/', $result, 
            "Comparison operator should be present for scenario: {$scenario}");
    }

    public static function edgeCaseDataProvider(): array
    {
        return [
            // Special numeric values
            'lt zero without NOT' => [['lt' => 0], false, 'less than zero', 'zero boundary'],
            'lt zero with NOT' => [['lt' => 0], true, 'greater equal zero', 'zero boundary negated'],
            'lte zero without NOT' => [['lte' => 0], false, 'less equal zero', 'zero boundary inclusive'],
            'lte zero with NOT' => [['lte' => 0], true, 'greater than zero', 'zero boundary inclusive negated'],
            
            // Negative values
            'lt negative without NOT' => [['lt' => -10], false, 'less than negative', 'negative value'],
            'lt negative with NOT' => [['lt' => -10], true, 'greater equal negative', 'negative value negated'],
            'lte negative without NOT' => [['lte' => -5], false, 'less equal negative', 'negative inclusive'],
            'lte negative with NOT' => [['lte' => -5], true, 'greater than negative', 'negative inclusive negated'],
            
            // Large numbers
            'lt large number' => [['lt' => 999999], false, 'less than large', 'large integer'],
            'lte large number' => [['lte' => 1000000], false, 'less equal large', 'large integer inclusive'],
            
            // String numbers
            'lt string number' => [['lt' => '42'], false, 'less than string number', 'string numeric value'],
            'lte string number' => [['lte' => '100'], false, 'less equal string number', 'string numeric inclusive'],
            
            // Complex scenarios
            'both operators present' => [
                ['lt' => 30, 'lte' => 40], 
                false, 
                'uses lt operator', 
                'multiple operators in config'
            ],
            'multi-key array' => [
                ['lt' => 50, 'ignored' => 'value'], 
                false, 
                'uses lt value only', 
                'multi-key array'
            ],
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
        $fieldConfig = ['lt' => $value];
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertParameterCreated($value, $expectedType);
    }

    public static function parameterTypeDataProvider(): array
    {
        return [
            'integer type' => [42, Connection::PARAM_INT, 'integer value'],
            'string type' => ['test', Connection::PARAM_STR, 'string value'],
            'boolean type' => [true, Connection::PARAM_BOOL, 'boolean value'],
            'null type' => [null, Connection::PARAM_NULL, 'null value'],
            'numeric string' => ['123', Connection::PARAM_STR, 'numeric string'],
            'float as string' => ['3.14', Connection::PARAM_STR, 'float string'],
        ];
    }

    /**
     * Test operator precedence when both lt and lte are present
     * 
     * @dataProvider operatorPrecedenceProvider
     */
    public function testOperatorPrecedence(
        array $fieldConfig,
        string $expectedOperator,
        mixed $expectedValue,
        string $scenario
    ): void {
        $fieldName = 'precedence_field';
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertStringContainsString($expectedOperator, $result, 
            "Operator precedence failed for scenario: {$scenario}");
        $this->assertParameterCreated($expectedValue);
    }

    public static function operatorPrecedenceProvider(): array
    {
        return [
            // When both are present, lte should take precedence based on current implementation
            'lte takes precedence' => [
                ['lt' => 30, 'lte' => 40],
                '<=',
                40,
                'lte has precedence over lt'
            ],
            'only lt present' => [
                ['lt' => 25],
                '<',
                25,
                'lt only in config'
            ],
            'only lte present' => [
                ['lte' => 35],
                '<=',
                35,
                'lte only in config'
            ],
        ];
    }

    /**
     * Test behavior with null history
     */
    public function testProcessWithNullHistory(): void
    {
        $fieldName = 'null_history_field';
        $fieldConfig = ['lt' => 50];
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, null);
        
        // Should behave as normal less than when no history
        $this->assertStringContainsString('<', $result);
        $this->assertStringNotContainsString('>', $result);
        $this->assertStringNotContainsString('=', $result);
    }

    /**
     * Test that operator correctly extracts value from array config
     */
    public function testArrayConfigValueExtraction(): void
    {
        $fieldName = 'extraction_field';
        $testValue = 75;
        $fieldConfig = ['lt' => $testValue, 'other_key' => 'should_be_ignored'];
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify that only the 'lt' value was used for parameter
        $this->assertParameterCreated($testValue);
    }

    /**
     * Test NOT operator logic consistency
     * 
     * @dataProvider notOperatorLogicProvider
     */
    public function testNotOperatorLogic(
        string $originalOp,
        mixed $value,
        bool $hasNot,
        string $expectedOp,
        string $scenario
    ): void {
        $fieldName = 'logic_field';
        $fieldConfig = [$originalOp => $value];
        $history = $this->createOperatorHistory($hasNot);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertStringContainsString($expectedOp, $result, 
            "NOT operator logic failed for scenario: {$scenario}");
    }

    public static function notOperatorLogicProvider(): array
    {
        return [
            // LT logic: < becomes >= when negated
            'lt normal' => ['lt', 30, false, '<', 'lt without NOT'],
            'lt negated' => ['lt', 30, true, '>=', 'lt with NOT becomes >='],
            
            // LTE logic: <= becomes > when negated  
            'lte normal' => ['lte', 40, false, '<=', 'lte without NOT'],
            'lte negated' => ['lte', 40, true, '>', 'lte with NOT becomes >'],
        ];
    }

    /**
     * Test comprehensive numeric boundary scenarios
     * 
     * @dataProvider numericBoundaryProvider
     */
    public function testNumericBoundaryScenarios(
        mixed $value,
        string $operator,
        string $expectedSqlOp,
        string $scenario
    ): void {
        $fieldName = 'boundary_field';
        $fieldConfig = [$operator => $value];
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertStringContainsString($expectedSqlOp, $result, 
            "Boundary scenario failed for {$scenario}");
        $this->assertParameterCreated($value);
    }

    public static function numericBoundaryProvider(): array
    {
        return [
            // Integer boundaries
            'zero lt' => [0, 'lt', '<', 'zero with lt'],
            'zero lte' => [0, 'lte', '<=', 'zero with lte'],
            'positive lt' => [100, 'lt', '<', 'positive with lt'],
            'positive lte' => [100, 'lte', '<=', 'positive with lte'],
            'negative lt' => [-50, 'lt', '<', 'negative with lt'],
            'negative lte' => [-50, 'lte', '<=', 'negative with lte'],
            
            // String numbers
            'string zero lt' => ['0', 'lt', '<', 'string zero with lt'],
            'string positive lte' => ['42', 'lte', '<=', 'string positive with lte'],
            'string negative lt' => ['-10', 'lt', '<', 'string negative with lt'],
        ];
    }
}