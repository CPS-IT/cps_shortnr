<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\AndOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\WrappingOperatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;

/**
 * Comprehensive test suite for AndOperator
 * Tests the wrapping operator pattern for AND logic operations
 * 
 * Based on config patterns like:
 * field: { eq: 'value', gte: 10, contains: 'text' }
 * Which creates: field = 'value' AND field >= 10 AND field LIKE '%text%'
 */
final class AndOperatorTest extends BaseOperatorTest
{
    private AndOperator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = new AndOperator();
    }

    public function testImplementsWrappingOperatorInterface(): void
    {
        $this->assertInstanceOf(WrappingOperatorInterface::class, $this->operator,
            'AndOperator must implement WrappingOperatorInterface');
    }

    /**
     * @dataProvider supportsDataProvider
     */
    public function testSupports(mixed $fieldConfig, bool $expectedSupport, string $scenario): void
    {
        $result = $this->operator->supports($fieldConfig);
        $this->assertEquals($expectedSupport, $result, "Support check failed for scenario: {$scenario}");
    }

    public static function supportsDataProvider(): array
    {
        return [
            // Should support non-list arrays with multiple elements
            'simple and conditions' => [['eq' => 'value', 'gte' => 10], true, 'multiple operators'],
            'complex and conditions' => [['contains' => 'test', 'starts' => 'prefix', 'isset' => true], true, 'three operators'],
            'mixed operator types' => [['eq' => 42, 'between' => [1, 100], 'match' => '^test'], true, 'diverse operators'],
            'nested conditions' => [['eq' => 'val', 'not' => ['contains' => 'bad']], true, 'nested operators'],
            
            // Should NOT support single element arrays
            'single element array' => [['eq' => 'value'], false, 'single operator'],
            'single nested element' => [['not' => ['eq' => 'test']], false, 'single nested operator'],
            
            // Should NOT support list arrays
            'sequential array' => [['active', 'pending'], false, 'list array'],
            'numeric list' => [[1, 2, 3], false, 'numeric list'],
            'mixed list' => [[1, 'two', 3], false, 'mixed list'],
            
            // Should NOT support empty arrays
            'empty array' => [[], false, 'empty array'],
            
            // Should NOT support non-array values
            'scalar string' => ['test', false, 'scalar string'],
            'scalar integer' => [42, false, 'scalar integer'],
            'null value' => [null, false, 'null value'],
            'boolean value' => [true, false, 'boolean value'],
        ];
    }

    /**
     * @dataProvider processDataProvider
     */
    public function testProcess(mixed $fieldConfig, string $scenario): void
    {
        $fieldName = 'test_field';
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // AndOperator process() method always returns null
        $this->assertNull($result, "Process should always return null for scenario: {$scenario}");
    }

    public static function processDataProvider(): array
    {
        return [
            'multiple conditions' => [['eq' => 'test', 'gte' => 5], 'standard AND config'],
            'empty config' => [[], 'empty configuration'],
            'single condition' => [['eq' => 'value'], 'single condition'],
            'complex nested' => [['eq' => 'val', 'not' => ['contains' => 'bad']], 'nested conditions'],
        ];
    }

    /**
     * @dataProvider postResultProcessDataProvider
     */
    public function testPostResultProcess(mixed $fieldConfig, array $result, string $scenario): void
    {
        $fieldName = 'test_field';
        $history = $this->createOperatorHistory(false);
        
        $processResult = $this->operator->postResultProcess($fieldName, $fieldConfig, $result, $history);
        
        // AndOperator postResultProcess() method always returns null
        $this->assertNull($processResult, "PostResultProcess should always return null for scenario: {$scenario}");
    }

    public static function postResultProcessDataProvider(): array
    {
        return [
            'standard result' => [['eq' => 'test'], [['id' => 1, 'name' => 'test']], 'normal result array'],
            'empty result' => [['gte' => 5], [], 'empty result'],
            'multiple conditions' => [['eq' => 'val', 'contains' => 'text'], [['data' => 'value']], 'multi-condition'],
        ];
    }

    /**
     * @dataProvider wrapDataProvider
     */
    public function testWrap(
        mixed $fieldConfig,
        array $callbackReturns,
        ?string $expectedResult,
        string $scenario
    ): void {
        $fieldName = 'wrap_field';
        $history = $this->createOperatorHistory(false);
        
        // Create mock callback that returns predefined values
        $callCount = 0;
        $callback = function() use (&$callCount, $callbackReturns) {
            return $callbackReturns[$callCount++] ?? null;
        };
        
        $result = $this->operator->wrap($fieldName, $fieldConfig, $this->queryBuilder, $history, $callback);
        
        if ($expectedResult === null) {
            $this->assertNull($result, "Wrap should return null for scenario: {$scenario}");
        } else {
            $this->assertInstanceOf(CompositeExpression::class, $result, 
                "Wrap should return CompositeExpression for scenario: {$scenario}");
            
            // Verify it's a valid composite expression (string should be wrapped in parentheses)
            $resultString = (string)$result;
            $this->assertStringStartsWith('(', $resultString,
                "Should create valid composite expression for scenario: {$scenario}");
            $this->assertStringEndsWith(')', $resultString,
                "Should create valid composite expression for scenario: {$scenario}");
        }
    }

    public static function wrapDataProvider(): array
    {
        return [
            // Empty config should return null
            'empty config' => [[], [], null, 'empty configuration'],
            
            // Single valid condition should create AND expression
            'single condition' => [
                ['eq' => 'value'],
                ['field = :param'],
                'and_expression',
                'single condition result'
            ],
            
            // Multiple conditions should create AND expression
            'multiple conditions' => [
                ['eq' => 'value', 'gte' => 10],
                ['field = :param1', 'field >= :param2'],
                'and_expression',
                'multiple conditions'
            ],
            
            // All null callback returns should return null
            'all null callbacks' => [
                ['eq' => 'value', 'contains' => 'text'],
                [null, null],
                null,
                'all conditions filtered out'
            ],
            
            // Mixed null/valid should create expression with valid ones
            'mixed null and valid' => [
                ['eq' => 'val', 'invalid' => 'test', 'gte' => 5],
                ['field = :param1', null, 'field >= :param2'],
                'and_expression',
                'filtered mixed conditions'
            ],
        ];
    }

    /**
     * @dataProvider postResultWrapDataProvider
     */
    public function testPostResultWrap(
        mixed $fieldConfig,
        array $inputResult,
        array $callbackReturns,
        ?array $expectedResult,
        string $scenario
    ): void {
        $fieldName = 'result_field';
        $history = $this->createOperatorHistory(false);
        
        // Create mock callback that returns predefined values
        $callCount = 0;
        $callback = function() use (&$callCount, $callbackReturns) {
            return $callbackReturns[$callCount++] ?? null;
        };
        
        $result = $this->operator->postResultWrap($fieldName, $fieldConfig, $inputResult, $history, $callback);
        
        $this->assertEquals($expectedResult, $result, "PostResultWrap failed for scenario: {$scenario}");
    }

    public static function postResultWrapDataProvider(): array
    {
        $testResult = [['id' => 1, 'name' => 'test']];
        
        return [
            // Empty config should return original result
            'empty config' => [[], $testResult, [], $testResult, 'empty configuration'],
            
            // All conditions pass should return original result
            'all conditions pass' => [
                ['eq' => 'value', 'gte' => 10],
                $testResult,
                [$testResult, $testResult], // Both callbacks return the result
                $testResult,
                'all conditions satisfied'
            ],
            
            // Any condition returns null should return null (AND logic)
            'first condition fails' => [
                ['eq' => 'value', 'gte' => 10],
                $testResult,
                [null, $testResult], // First callback returns null
                null,
                'first condition fails'
            ],
            
            'second condition fails' => [
                ['eq' => 'value', 'gte' => 10],
                $testResult,
                [$testResult, null], // Second callback returns null
                null,
                'second condition fails'
            ],
            
            'all conditions fail' => [
                ['eq' => 'value', 'contains' => 'text'],
                $testResult,
                [null, null], // Both callbacks return null
                null,
                'all conditions fail'
            ],
            
            // Complex scenario with multiple conditions
            'complex mixed scenario' => [
                ['eq' => 'val', 'gte' => 5, 'contains' => 'text'],
                $testResult,
                [$testResult, $testResult, $testResult], // All pass
                $testResult,
                'complex passing scenario'
            ],
        ];
    }

    /**
     * Test operator history creation and management
     */
    public function testOperatorHistoryManagement(): void
    {
        $fieldName = 'history_field';
        $fieldConfig = ['eq' => 'value', 'gte' => 10];
        $parentHistory = $this->createOperatorHistory(false);
        
        $historyCreated = false;
        $callback = function($fn, $fc, $qb, $history) use (&$historyCreated) {
            // Verify that OperatorHistory was created with correct parent and operator
            $this->assertInstanceOf(OperatorHistoryInterface::class, $history,
                'Callback should receive OperatorHistoryInterface');
                
            // Since we can't easily test the internal structure, we verify the type
            $this->assertInstanceOf(OperatorHistory::class, $history,
                'Should create concrete OperatorHistory instance');
                
            $historyCreated = true;
            return 'test_expression';
        };
        
        $result = $this->operator->wrap($fieldName, $fieldConfig, $this->queryBuilder, $parentHistory, $callback);
        
        $this->assertTrue($historyCreated, 'Operator history should be created and passed to callback');
        $this->assertInstanceOf(CompositeExpression::class, $result, 'Should return CompositeExpression');
    }

    /**
     * Test behavior with various field names
     * 
     * @dataProvider fieldNameVariationsProvider
     */
    public function testWrapWithVariousFieldNames(string $fieldName): void
    {
        $fieldConfig = ['eq' => 'test', 'gte' => 5];
        $history = $this->createOperatorHistory(false);
        
        $callback = function($fn, $fc, $qb, $hist) use ($fieldName) {
            // Verify field name is passed correctly to callback
            $this->assertEquals($fieldName, $fn, 'Field name should be passed to callback');
            return 'mock_expression';
        };
        
        $result = $this->operator->wrap($fieldName, $fieldConfig, $this->queryBuilder, $history, $callback);
        
        $this->assertInstanceOf(CompositeExpression::class, $result,
            "Should handle field name '{$fieldName}' correctly");
    }

    public static function fieldNameVariationsProvider(): array
    {
        return self::fieldNameProvider();
    }

    /**
     * Test null parent history handling
     */
    public function testNullParentHistory(): void
    {
        $fieldName = 'null_parent_field';
        $fieldConfig = ['eq' => 'value'];
        
        $callback = function($fn, $fc, $qb, $history) {
            $this->assertInstanceOf(OperatorHistoryInterface::class, $history,
                'Should create history even with null parent');
            return 'test_expression';
        };
        
        $result = $this->operator->wrap($fieldName, $fieldConfig, $this->queryBuilder, null, $callback);
        
        $this->assertInstanceOf(CompositeExpression::class, $result,
            'Should work correctly with null parent history');
    }

    /**
     * Test edge cases and error conditions
     * 
     * @dataProvider edgeCaseDataProvider
     */
    public function testEdgeCases(
        mixed $fieldConfig,
        array $callbackReturns,
        string $expectedBehavior,
        string $scenario
    ): void {
        $fieldName = 'edge_case_field';
        $history = $this->createOperatorHistory(false);
        
        $callCount = 0;
        $callback = function() use (&$callCount, $callbackReturns) {
            return $callbackReturns[$callCount++] ?? null;
        };
        
        $result = $this->operator->wrap($fieldName, $fieldConfig, $this->queryBuilder, $history, $callback);
        
        if ($expectedBehavior === 'null') {
            $this->assertNull($result, "Should return null for scenario: {$scenario}");
        } else {
            $this->assertInstanceOf(CompositeExpression::class, $result,
                "Should return CompositeExpression for scenario: {$scenario}");
        }
    }

    public static function edgeCaseDataProvider(): array
    {
        return [
            'single element array' => [
                ['eq' => 'value'],
                ['field = :param'],
                'expression',
                'single element still creates AND'
            ],
            
            'all empty string returns' => [
                ['eq' => 'val1', 'gte' => 'val2'],
                ['', ''],
                'null',
                'empty string expressions filtered out'
            ],
            
            'mixed empty and valid' => [
                ['eq' => 'val1', 'contains' => 'val2', 'gte' => 'val3'],
                ['field = :param1', '', 'field >= :param3'],
                'expression',
                'mixed empty filtered correctly'
            ],
        ];
    }
}