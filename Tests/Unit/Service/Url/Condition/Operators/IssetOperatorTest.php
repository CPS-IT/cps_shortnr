<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\IssetOperator;

/**
 * Comprehensive test suite for IssetOperator
 * Tests field existence checking with NULL/NOT NULL operations
 * 
 * Based on config syntax: surname: { isset: true }
 */
final class IssetOperatorTest extends BaseOperatorTest
{
    private IssetOperator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = new IssetOperator();
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
            // Arrays with 'isset' key (should be supported)
            'isset true' => [['isset' => true], true, 'isset with boolean true'],
            'isset false' => [['isset' => false], true, 'isset with boolean false'],
            'isset string true' => [['isset' => 'true'], true, 'isset with string true'],
            'isset string false' => [['isset' => 'false'], true, 'isset with string false'],
            'isset integer 1' => [['isset' => 1], true, 'isset with integer 1'],
            'isset integer 0' => [['isset' => 0], true, 'isset with integer 0'],
            'isset null' => [['isset' => null], true, 'isset with null value'],
            'complex array with isset' => [['isset' => true, 'other' => 'ignored'], true, 'complex array with isset'],
            
            // Arrays without 'isset' key (should not be supported)
            'empty array' => [[], false, 'empty array'],
            'array without isset' => [['contains' => 'test'], false, 'array with other operators'],
            'list array' => [['active', 'pending'], false, 'sequential array'],
            'complex array without isset' => [['not' => ['eq' => 'test']], false, 'nested without isset'],
            
            // Scalar values (should not be supported - isset requires array syntax)
            'scalar true' => [true, false, 'scalar boolean true'],
            'scalar false' => [false, false, 'scalar boolean false'],
            'scalar string' => ['test', false, 'scalar string'],
            'scalar integer' => [42, false, 'scalar integer'],
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
        string $scenario
    ): void {
        $fieldName = 'test_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify SQL expression format for NULL checks
        $expectedPattern = "/^{$fieldName} {$expectedOperator}$/";
        $this->assertSqlExpression($expectedPattern, $result);
        
        // NULL checks don't create parameters
        $parameters = $this->queryBuilderHelper->getCreatedParameters();
        $this->assertEmpty($parameters, "NULL checks should not create parameters");
    }

    public static function processDataProvider(): array
    {
        $scenarios = [];
        
        // Test different isset values with and without NOT operator
        $issetValues = [
            'true boolean' => ['value' => true, 'truthy' => true],
            'false boolean' => ['value' => false, 'truthy' => false],
            'string true' => ['value' => 'true', 'truthy' => true],
            'string false' => ['value' => 'false', 'truthy' => true], // Non-empty string is truthy
            'empty string' => ['value' => '', 'truthy' => false],
            'integer 1' => ['value' => 1, 'truthy' => true],
            'integer 0' => ['value' => 0, 'truthy' => false],
            'integer 42' => ['value' => 42, 'truthy' => true],
            'null value' => ['value' => null, 'truthy' => false],
            'array non-empty' => ['value' => ['test'], 'truthy' => true],
            'array empty' => ['value' => [], 'truthy' => false],
        ];
        
        foreach ($issetValues as $valueKey => $valueData) {
            $isTruthy = $valueData['truthy'];
            
            // Without NOT operator
            $scenarios["{$valueKey} without NOT"] = [
                'fieldConfig' => ['isset' => $valueData['value']],
                'hasNotInHistory' => false,
                'expectedOperator' => $isTruthy ? 'IS NOT NULL' : 'IS NULL',
                'scenario' => "{$valueKey} normal isset check",
            ];
            
            // With NOT operator (logic inverted)
            $scenarios["{$valueKey} with NOT"] = [
                'fieldConfig' => ['isset' => $valueData['value']],
                'hasNotInHistory' => true,
                'expectedOperator' => $isTruthy ? 'IS NULL' : 'IS NOT NULL',
                'scenario' => "{$valueKey} negated isset check",
            ];
        }
        
        return $scenarios;
    }

    /**
     * @dataProvider fieldNameVariationsProvider
     */
    public function testProcessWithVariousFieldNames(string $fieldName): void
    {
        $fieldConfig = ['isset' => true];
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify field name is preserved in expression
        $this->assertStringContainsString($fieldName, $result, 
            "Field name '{$fieldName}' not found in expression");
        $this->assertStringContainsString('IS NOT NULL', $result, 
            "IS NOT NULL operator not found in expression");
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
        
        // Verify expression contains field name and NULL operator
        $this->assertStringContainsString($fieldName, $result, 
            "Field name should be in expression for scenario: {$scenario}");
        $this->assertMatchesRegularExpression('/IS (NOT )?NULL/', $result, 
            "Expression should contain NULL check for scenario: {$scenario}");
    }

    public static function edgeCaseDataProvider(): array
    {
        return [
            // Special boolean interpretations
            'isset true without NOT' => [['isset' => true], false, 'checks IS NOT NULL', 'true value checks existence'],
            'isset true with NOT' => [['isset' => true], true, 'checks IS NULL', 'true value negated checks non-existence'],
            'isset false without NOT' => [['isset' => false], false, 'checks IS NULL', 'false value checks non-existence'],
            'isset false with NOT' => [['isset' => false], true, 'checks IS NOT NULL', 'false value negated checks existence'],
            
            // Truthy/falsy values
            'isset non-empty string' => [['isset' => 'test'], false, 'checks IS NOT NULL', 'truthy string'],
            'isset empty string' => [['isset' => ''], false, 'checks IS NULL', 'falsy empty string'],
            'isset positive number' => [['isset' => 42], false, 'checks IS NOT NULL', 'truthy number'],
            'isset zero' => [['isset' => 0], false, 'checks IS NULL', 'falsy zero'],
            
            // Complex scenarios with additional keys
            'multi-key array with isset' => [['isset' => true, 'ignored' => 'value'], false, 'checks IS NOT NULL', 'multi-key array'],
        ];
    }

    /**
     * Test boolean casting behavior for isset values
     * 
     * @dataProvider booleanCastingProvider
     */
    public function testIssetBooleanCasting(mixed $issetValue, bool $expectedTruthy, string $description): void
    {
        $fieldName = 'boolean_test_field';
        $fieldConfig = ['isset' => $issetValue];
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $expectedOperator = $expectedTruthy ? 'IS NOT NULL' : 'IS NULL';
        $this->assertStringContainsString($expectedOperator, $result, 
            "Boolean casting failed for {$description}: expected {$expectedOperator}");
    }

    public static function booleanCastingProvider(): array
    {
        return [
            'boolean true' => [true, true, 'explicit boolean true'],
            'boolean false' => [false, false, 'explicit boolean false'],
            'integer 1' => [1, true, 'integer 1 (truthy)'],
            'integer 0' => [0, false, 'integer 0 (falsy)'],
            'integer -1' => [-1, true, 'negative integer (truthy)'],
            'non-empty string' => ['test', true, 'non-empty string (truthy)'],
            'empty string' => ['', false, 'empty string (falsy)'],
            'string zero' => ['0', false, 'string "0" (falsy in PHP)'],
            'null value' => [null, false, 'null value (falsy)'],
            'non-empty array' => [['item'], true, 'non-empty array (truthy)'],
            'empty array' => [[], false, 'empty array (falsy)'],
            'float positive' => [3.14, true, 'positive float (truthy)'],
            'float zero' => [0.0, false, 'zero float (falsy)'],
        ];
    }

    /**
     * Test behavior with null history
     */
    public function testProcessWithNullHistory(): void
    {
        $fieldName = 'null_history_field';
        $fieldConfig = ['isset' => true];
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, null);
        
        // Should behave as normal isset check when no history
        $this->assertStringContainsString('IS NOT NULL', $result);
        $this->assertStringNotContainsString('IS NULL', $result);
    }

    /**
     * Test that operator correctly extracts isset value from array config
     */
    public function testArrayConfigValueExtraction(): void
    {
        $fieldName = 'extraction_field';
        $fieldConfig = ['isset' => true, 'other_key' => 'should_be_ignored'];
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify that only the 'isset' value was used
        $this->assertStringContainsString('IS NOT NULL', $result, 
            "Should use isset value and ignore other keys");
        $this->assertStringContainsString($fieldName, $result, 
            "Should use correct field name");
    }

    /**
     * Test behavior consistency across NOT operator combinations
     * 
     * @dataProvider notOperatorConsistencyProvider
     */
    public function testNotOperatorConsistency(
        bool $issetValue, 
        bool $hasNot, 
        string $expectedOperator, 
        string $scenario
    ): void {
        $fieldName = 'consistency_field';
        $fieldConfig = ['isset' => $issetValue];
        $history = $this->createOperatorHistory($hasNot);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertStringContainsString($expectedOperator, $result, 
            "NOT operator consistency failed for scenario: {$scenario}");
    }

    public static function notOperatorConsistencyProvider(): array
    {
        return [
            // Logic: isset=true means "check exists" -> IS NOT NULL
            //        isset=false means "check not exists" -> IS NULL
            //        NOT inverts the final operation
            'isset true, no NOT' => [true, false, 'IS NOT NULL', 'true without NOT'],
            'isset true, with NOT' => [true, true, 'IS NULL', 'true with NOT'],
            'isset false, no NOT' => [false, false, 'IS NULL', 'false without NOT'],
            'isset false, with NOT' => [false, true, 'IS NOT NULL', 'false with NOT'],
        ];
    }
}