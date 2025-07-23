<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\NotOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\WrappingOperatorInterface;

/**
 * Comprehensive test suite for NotOperator
 * Tests the wrapping operator pattern and history management
 */
final class NotOperatorTest extends BaseOperatorTest
{
    private NotOperator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = new NotOperator();
    }

    public function testImplementsWrappingOperatorInterface(): void
    {
        $this->assertInstanceOf(WrappingOperatorInterface::class, $this->operator,
            'NotOperator must implement WrappingOperatorInterface');
    }

    /**
     * @dataProvider supportDataProvider
     */
    public function testSupport(mixed $fieldConfig, bool $expectedSupport, string $scenario): void
    {
        $result = $this->operator->support($fieldConfig);
        $this->assertEquals($expectedSupport, $result, 
            "Support check failed for scenario: {$scenario}");
    }

    public static function supportDataProvider(): array
    {
        return [
            // Should support arrays with 'not' key
            'simple not array' => [['not' => 'value'], true, 'array with not key'],
            'not with eq' => [['not' => ['eq' => 'test']], true, 'not wrapping eq operator'],
            'not with contains' => [['not' => ['contains' => 'test']], true, 'not wrapping contains'],
            'not with array' => [['not' => ['active', 'pending']], true, 'not wrapping array values'],
            'not with complex' => [['not' => ['gte' => 50, 'lte' => 100]], true, 'not wrapping complex conditions'],
            'empty not' => [['not' => []], true, 'not with empty content'],
            'not with null' => [['not' => null], true, 'not with null value'],
            
            // Should not support other structures
            'no not key' => [['eq' => 'test'], false, 'array without not key'],
            'empty array' => [[], false, 'empty array'],
            'scalar value' => ['test', false, 'scalar string'],
            'integer value' => [42, false, 'scalar integer'],
            'null value' => [null, false, 'null value'],
            'boolean value' => [true, false, 'boolean value'],
            'list array' => [['active', 'pending'], false, 'sequential array'],
            'multiple keys with not' => [['not' => 'test', 'eq' => 'other'], true, 'multiple keys but has not'],
            'not as value not key' => [['eq' => 'not'], false, 'not as value not key'],
        ];
    }

    /**
     * Test that process method returns null (as per implementation)
     */
    public function testProcessReturnsNull(): void
    {
        $fieldName = 'test_field';
        $fieldConfig = ['not' => ['eq' => 'test']];
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertNull($result, 'NotOperator process method should return null');
    }

    /**
     * Test process with various field configurations
     * 
     * @dataProvider processDataProvider
     */
    public function testProcessWithVariousConfigs(mixed $fieldConfig, string $scenario): void
    {
        $fieldName = 'test_field';
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertNull($result, "Process should return null for scenario: {$scenario}");
    }

    public static function processDataProvider(): array
    {
        return [
            'simple not' => [['not' => 'value'], 'simple not value'],
            'not with array' => [['not' => ['eq' => 'test']], 'not with nested operator'],
            'complex not' => [['not' => ['contains' => 'test', 'eq' => 'other']], 'complex nested conditions'],
            'not with list' => [['not' => ['active', 'pending']], 'not with array values'],
        ];
    }

    /**
     * @dataProvider wrapDataProvider
     */
    public function testWrap(
        mixed $fieldConfig,
        string $expectedNestedConfig,
        string $scenario
    ): void {
        $fieldName = 'wrap_field';
        $originalHistory = $this->createOperatorHistory(false);
        $callbackExecuted = false;
        $receivedArguments = [];
        
        // Create a callback that captures the arguments
        $nestedCallback = function($field, $config, $queryBuilder, $history) use (&$callbackExecuted, &$receivedArguments) {
            $callbackExecuted = true;
            $receivedArguments = [
                'fieldName' => $field,
                'fieldConfig' => $config,
                'queryBuilder' => $queryBuilder,
                'history' => $history,
            ];
            return ['callback_result'];
        };
        
        $result = $this->operator->wrap(
            $fieldName, 
            $fieldConfig, 
            $this->queryBuilder, 
            $originalHistory, 
            $nestedCallback
        );
        
        // Verify callback was executed
        $this->assertTrue($callbackExecuted, "Nested callback should be executed for scenario: {$scenario}");
        
        // Verify callback received correct arguments
        $this->assertEquals($fieldName, $receivedArguments['fieldName'], 
            "Field name should be passed to callback");
        $this->assertEquals($fieldConfig['not'], $receivedArguments['fieldConfig'], 
            "Should pass the 'not' content to callback");
        $this->assertSame($this->queryBuilder, $receivedArguments['queryBuilder'], 
            "QueryBuilder should be passed to callback");
        
        // Verify history was enhanced with NotOperator
        $this->assertInstanceOf(OperatorHistoryInterface::class, $receivedArguments['history'],
            "History should be provided to callback");
        $this->assertNotSame($originalHistory, $receivedArguments['history'],
            "History should be a new instance with NotOperator added");
        
        // Verify result is returned from callback
        $this->assertEquals(['callback_result'], $result, 
            "Should return callback result");
    }

    public static function wrapDataProvider(): array
    {
        return [
            'simple not value' => [
                ['not' => 'simple_value'],
                'simple_value',
                'simple not wrapping'
            ],
            'not with eq operator' => [
                ['not' => ['eq' => 'test']],
                "['eq' => 'test']",
                'not wrapping equality operator'
            ],
            'not with contains' => [
                ['not' => ['contains' => 'search_term']],
                "['contains' => 'search_term']",
                'not wrapping contains operator'
            ],
            'not with array values' => [
                ['not' => ['active', 'pending', 'draft']],
                "['active', 'pending', 'draft']",
                'not wrapping array values'
            ],
            'not with complex conditions' => [
                ['not' => ['gte' => 50, 'contains' => 'test']],
                "['gte' => 50, 'contains' => 'test']",
                'not wrapping complex conditions'
            ],
            'not with nested structure' => [
                ['not' => ['not' => ['eq' => 'double_negation']]],
                "['not' => ['eq' => 'double_negation']]",
                'double negation scenario'
            ],
        ];
    }

    /**
     * Test that wrap creates proper OperatorHistory
     */
    public function testWrapCreatesCorrectHistory(): void
    {
        $fieldName = 'history_test_field';
        $fieldConfig = ['not' => ['eq' => 'test']];
        $originalHistory = $this->createOperatorHistory(false);
        
        $historyReceived = null;
        $callback = function($field, $config, $queryBuilder, $history) use (&$historyReceived) {
            $historyReceived = $history;
            return [];
        };
        
        $this->operator->wrap($fieldName, $fieldConfig, $this->queryBuilder, $originalHistory, $callback);
        
        // Verify history instance
        $this->assertInstanceOf(OperatorHistory::class, $historyReceived,
            "Should create OperatorHistory instance");
        
        // Verify that the new history contains NotOperator
        $this->assertTrue($historyReceived->hasOperatorTypeInHistory(NotOperator::class),
            "New history should contain NotOperator");
    }

    /**
     * Test wrap with null parent history
     */
    public function testWrapWithNullParentHistory(): void
    {
        $fieldName = 'null_parent_field';
        $fieldConfig = ['not' => ['eq' => 'test']];
        
        $historyReceived = null;
        $callback = function($field, $config, $queryBuilder, $history) use (&$historyReceived) {
            $historyReceived = $history;
            return [];
        };
        
        $this->operator->wrap($fieldName, $fieldConfig, $this->queryBuilder, null, $callback);
        
        // Should still create history even with null parent
        $this->assertInstanceOf(OperatorHistoryInterface::class, $historyReceived,
            "Should create history even with null parent");
        $this->assertTrue($historyReceived->hasOperatorTypeInHistory(NotOperator::class),
            "History should contain NotOperator even with null parent");
    }

    /**
     * Test wrap with various field names
     * 
     * @dataProvider fieldNameVariationsProvider
     */
    public function testWrapWithVariousFieldNames(string $fieldName): void
    {
        $fieldConfig = ['not' => ['eq' => 'test']];
        $originalHistory = $this->createOperatorHistory(false);
        
        $receivedFieldName = null;
        $callback = function($field, $config, $queryBuilder, $history) use (&$receivedFieldName) {
            $receivedFieldName = $field;
            return [];
        };
        
        $this->operator->wrap($fieldName, $fieldConfig, $this->queryBuilder, $originalHistory, $callback);
        
        $this->assertEquals($fieldName, $receivedFieldName,
            "Field name '{$fieldName}' should be passed correctly to callback");
    }

    public static function fieldNameVariationsProvider(): array
    {
        return self::fieldNameProvider();
    }

    /**
     * Test wrap behavior with complex nested configurations
     */
    public function testWrapWithComplexNesting(): void
    {
        $fieldName = 'complex_field';
        $complexConfig = [
            'not' => [
                'contains' => 'test',
                'gte' => 50,
                'not' => ['eq' => 'inner']
            ]
        ];
        
        $receivedConfig = null;
        $callback = function($field, $config, $queryBuilder, $history) use (&$receivedConfig) {
            $receivedConfig = $config;
            return ['complex_result'];
        };
        
        $result = $this->operator->wrap($fieldName, $complexConfig, $this->queryBuilder, null, $callback);
        
        // Verify that the entire 'not' content is passed
        $expectedConfig = [
            'contains' => 'test',
            'gte' => 50,
            'not' => ['eq' => 'inner']
        ];
        $this->assertEquals($expectedConfig, $receivedConfig,
            "Complex nested config should be passed correctly");
        
        $this->assertEquals(['complex_result'], $result,
            "Should return callback result for complex config");
    }

    /**
     * Test edge cases in wrap method
     * 
     * @dataProvider wrapEdgeCaseDataProvider
     */
    public function testWrapEdgeCases(mixed $fieldConfig, string $scenario): void
    {
        $fieldName = 'edge_case_field';
        
        $callback = function($field, $config, $queryBuilder, $history) {
            return ['edge_case_result'];
        };
        
        $result = $this->operator->wrap($fieldName, $fieldConfig, $this->queryBuilder, null, $callback);
        
        $this->assertEquals(['edge_case_result'], $result,
            "Should handle edge case: {$scenario}");
    }

    public static function wrapEdgeCaseDataProvider(): array
    {
        return [
            'not with null value' => [['not' => null], 'null value in not'],
            'not with empty array' => [['not' => []], 'empty array in not'],
            'not with boolean' => [['not' => true], 'boolean value in not'],
            'not with integer' => [['not' => 42], 'integer value in not'],
            'not with nested empty' => [['not' => ['eq' => '']], 'empty string in nested eq'],
        ];
    }
}