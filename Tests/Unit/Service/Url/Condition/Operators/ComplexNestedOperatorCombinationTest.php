<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\ArrayInOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\BetweenOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\EqualOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\GreaterOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\IssetOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\LessOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\NotOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\RegexMatchOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\StringContainsOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\StringEndsOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\StringStartsOperator;

/**
 * Test complex nested operator combinations that simulate real-world config.yaml scenarios
 * This tests the complex nesting logic and operator interactions from the configuration
 */
final class ComplexNestedOperatorCombinationTest extends BaseOperatorTest
{
    private array $operators;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize all operators for complex testing
        $this->operators = [
            'equal' => new EqualOperator(),
            'arrayIn' => new ArrayInOperator(),
            'stringContains' => new StringContainsOperator(),
            'stringStarts' => new StringStartsOperator(),
            'stringEnds' => new StringEndsOperator(),
            'greater' => new GreaterOperator(),
            'less' => new LessOperator(),
            'between' => new BetweenOperator(),
            'regex' => new RegexMatchOperator(),
            'isset' => new IssetOperator(),
            'not' => new NotOperator(),
        ];
    }

    /**
     * Test complex scenario from config.yaml: is_event field with contains and NOT eq combination
     * 
     * is_event:
     *   contains: "test2"
     *   not:
     *     eq: 1
     *     contains: "test"
     */
    public function testConfigYamlIsEventScenario(): void
    {
        // This would be processed as multiple separate conditions by the condition system
        $fieldName = 'is_event';
        
        // Test the contains part
        $containsConfig = ['contains' => 'test2'];
        $result1 = $this->operators['stringContains']->process(
            $fieldName, $containsConfig, $this->queryBuilder, null
        );
        $this->assertStringContainsString('LIKE', $result1);
        $this->assertParameterCreated('%test2%');
        
        // Reset for next test
        $this->queryBuilderHelper->reset();
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
        
        // Test the NOT eq part
        $history = $this->createOperatorHistory(true); // Simulate NOT in history
        $eqConfig = ['eq' => 1];
        $result2 = $this->operators['equal']->process(
            $fieldName, $eqConfig, $this->queryBuilder, $history
        );
        $this->assertStringContainsString('!=', $result2);
        $this->assertParameterCreated(1);
        
        // Reset for next test
        $this->queryBuilderHelper->reset();
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
        
        // Test the NOT contains part
        $notContainsConfig = ['contains' => 'test'];
        $result3 = $this->operators['stringContains']->process(
            $fieldName, $notContainsConfig, $this->queryBuilder, $history
        );
        $this->assertStringContainsString('NOT LIKE', $result3);
        $this->assertParameterCreated('%test%');
    }

    /**
     * Test complex range and comparison combinations from config.yaml
     * 
     * score:
     *   gte: 50
     * ranking:
     *   lt: 30
     * age:
     *   not:
     *     between: [18, 65]
     */
    public function testConfigYamlRangeComparisons(): void
    {
        // Test gte (greater than or equal)
        $fieldName = 'score';
        $gteConfig = ['gte' => 50];
        $result1 = $this->operators['greater']->process(
            $fieldName, $gteConfig, $this->queryBuilder, null
        );
        $this->assertStringContainsString('>=', $result1);
        $this->assertParameterCreated(50);
        
        // Reset for next test
        $this->queryBuilderHelper->reset();
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
        
        // Test lt (less than)
        $fieldName = 'ranking';
        $ltConfig = ['lt' => 30];
        $result2 = $this->operators['less']->process(
            $fieldName, $ltConfig, $this->queryBuilder, null
        );
        $this->assertStringContainsString('<', $result2);
        $this->assertParameterCreated(30);
        
        // Reset for next test
        $this->queryBuilderHelper->reset();
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
        
        // Test NOT between
        $fieldName = 'age';
        $betweenConfig = ['between' => [18, 65]];
        $history = $this->createOperatorHistory(true); // Simulate NOT in history
        $result3 = $this->operators['between']->process(
            $fieldName, $betweenConfig, $this->queryBuilder, $history
        );
        $this->assertStringContainsString('NOT BETWEEN', $result3);
        $this->assertParameterCreated(18); // First parameter
    }

    /**
     * Test complex string operations from config.yaml
     * 
     * version:
     *   not:
     *     ends: '-rc'
     *   starts: 'v'
     * street:
     *   ends: 'road'
     */
    public function testConfigYamlStringOperations(): void
    {
        // Test NOT ends with
        $fieldName = 'version';
        $endsConfig = ['ends' => '-rc'];
        $history = $this->createOperatorHistory(true); // Simulate NOT in history
        $result1 = $this->operators['stringEnds']->process(
            $fieldName, $endsConfig, $this->queryBuilder, $history
        );
        $this->assertStringContainsString('NOT LIKE', $result1);
        $this->assertParameterCreated('%-rc');
        
        // Reset for next test
        $this->queryBuilderHelper->reset();
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
        
        // Test starts with (without NOT)
        $startsConfig = ['starts' => 'v'];
        $result2 = $this->operators['stringStarts']->process(
            $fieldName, $startsConfig, $this->queryBuilder, null
        );
        $this->assertStringContainsString('LIKE', $result2);
        $this->assertParameterCreated('v%');
        
        // Reset for next test
        $this->queryBuilderHelper->reset();
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
        
        // Test ends with (normal case)
        $fieldName = 'street';
        $endsConfig = ['ends' => 'road'];
        $result3 = $this->operators['stringEnds']->process(
            $fieldName, $endsConfig, $this->queryBuilder, null
        );
        $this->assertStringContainsString('LIKE', $result3);
        $this->assertParameterCreated('%road');
    }

    /**
     * Test array operations and negations from config.yaml
     * 
     * status: ["active", "pending"]     # implicit IN
     * blocked_users:
     *   not: ["spam", "bot"]           # opposite of implicit IN
     */
    public function testConfigYamlArrayOperations(): void
    {
        // Test implicit IN (list array)
        $fieldName = 'status';
        $statusArray = ['active', 'pending'];
        $result1 = $this->operators['arrayIn']->process(
            $fieldName, $statusArray, $this->queryBuilder, null
        );
        $this->assertStringContainsString('IN', $result1);
        $this->assertParameterCreated(['active', 'pending']);
        
        // Reset for next test
        $this->queryBuilderHelper->reset();
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
        
        // Test NOT IN (negated array)
        $fieldName = 'blocked_users';
        $blockedArray = ['spam', 'bot'];
        $history = $this->createOperatorHistory(true); // Simulate NOT in history
        $result2 = $this->operators['arrayIn']->process(
            $fieldName, $blockedArray, $this->queryBuilder, $history
        );
        $this->assertStringContainsString('NOT IN', $result2);
        $this->assertParameterCreated(['spam', 'bot']);
    }

    /**
     * Test regex and isset operations from config.yaml
     * 
     * email:
     *   match: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
     * surname:
     *   isset: true
     */
    public function testConfigYamlRegexAndIsset(): void
    {
        // Test regex matching
        $fieldName = 'email';
        $emailRegex = '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$';
        $regexConfig = ['match' => $emailRegex];
        $result1 = $this->operators['regex']->process(
            $fieldName, $regexConfig, $this->queryBuilder, null
        );
        $this->assertStringContainsString('REGEXP', $result1);
        $this->assertParameterCreated($emailRegex);
        
        // Reset for next test
        $this->queryBuilderHelper->reset();
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
        
        // Test isset operation
        $fieldName = 'surname';
        $issetConfig = ['isset' => true];
        $result2 = $this->operators['isset']->process(
            $fieldName, $issetConfig, $this->queryBuilder, null
        );
        $this->assertStringContainsString('IS NOT NULL', $result2);
    }

    /**
     * @dataProvider complexNestedScenarioProvider
     */
    public function testComplexNestedScenarios(
        string $fieldName,
        array $config,
        string $operatorType,
        bool $shouldHaveNot,
        mixed $expectedValue,
        string $expectedSqlPattern,
        string $scenario
    ): void {
        $history = $this->createOperatorHistory($shouldHaveNot);
        $operator = $this->operators[$operatorType];
        
        $result = $operator->process($fieldName, $config, $this->queryBuilder, $history);
        
        $this->assertMatchesRegularExpression($expectedSqlPattern, $result, 
            "SQL pattern mismatch for scenario: {$scenario}");
        
        if ($expectedValue !== null) {
            $this->assertParameterCreated($expectedValue);
        }
    }

    public static function complexNestedScenarioProvider(): array
    {
        return [
            // String operations with NOT
            'NOT contains with wildcards' => [
                'field_name' => 'description',
                'config' => ['contains' => 'test%_pattern'],
                'operatorType' => 'stringContains',
                'shouldHaveNot' => true,
                'expectedValue' => '%test\\%\\_pattern%',
                'expectedSqlPattern' => '/description NOT LIKE :dcValue\d+/',
                'scenario' => 'NOT contains with wildcard escaping'
            ],
            
            // Comparison with NOT
            'NOT greater than' => [
                'field_name' => 'age',
                'config' => ['gte' => 18],
                'operatorType' => 'greater',
                'shouldHaveNot' => true,
                'expectedValue' => 18,
                'expectedSqlPattern' => '/age < :dcValue\d+/', // NOT >= becomes <
                'scenario' => 'NOT greater than or equal'
            ],
            
            // Array with NOT
            'NOT in array' => [
                'field_name' => 'roles',
                'config' => ['admin', 'moderator'],
                'operatorType' => 'arrayIn',
                'shouldHaveNot' => true,
                'expectedValue' => ['admin', 'moderator'],
                'expectedSqlPattern' => '/roles NOT IN \\(:dcValue\d+\\)/',
                'scenario' => 'NOT in array operation'
            ],
            
            // Complex string starts with NOT
            'NOT starts with special chars' => [
                'field_name' => 'username',
                'config' => ['starts' => 'admin_'],
                'operatorType' => 'stringStarts',
                'shouldHaveNot' => true,
                'expectedValue' => 'admin\\_%',
                'expectedSqlPattern' => '/username NOT LIKE :dcValue\d+/',
                'scenario' => 'NOT starts with underscore escaping'
            ],
            
            // Range operations
            'between range normal' => [
                'field_name' => 'price',
                'config' => ['between' => [10.99, 99.99]],
                'operatorType' => 'between',
                'shouldHaveNot' => false,
                'expectedValue' => 10.99,
                'expectedSqlPattern' => '/price BETWEEN :dcValue\d+ AND :dcValue\d+/',
                'scenario' => 'between range operation'
            ],
            
            // Regex with NOT
            'NOT regex match' => [
                'field_name' => 'code',
                'config' => ['match' => '^[A-Z]{3}-\\d{3}$'],
                'operatorType' => 'regex',
                'shouldHaveNot' => true,
                'expectedValue' => '^[A-Z]{3}-\\d{3}$',
                'expectedSqlPattern' => '/code NOT REGEXP :dcValue\d+/',
                'scenario' => 'NOT regex pattern matching'
            ],
            
            // Isset with NOT
            'NOT isset (should be null)' => [
                'field_name' => 'optional_field',
                'config' => ['isset' => true],
                'operatorType' => 'isset',
                'shouldHaveNot' => true,
                'expectedValue' => null, // isset doesn't create parameters
                'expectedSqlPattern' => '/optional_field IS NULL/',
                'scenario' => 'NOT isset becomes IS NULL'
            ],
            
            // Equality with complex types
            'NOT equal with null' => [
                'field_name' => 'deleted_at',
                'config' => ['eq' => null],
                'operatorType' => 'equal',
                'shouldHaveNot' => true,
                'expectedValue' => null,
                'expectedSqlPattern' => '/deleted_at != :dcValue\d+/',
                'scenario' => 'NOT equal to null'
            ],
            
            // String ends with complex pattern
            'ends with URL pattern' => [
                'field_name' => 'url',
                'config' => ['ends' => '.html'],
                'operatorType' => 'stringEnds',
                'shouldHaveNot' => false,
                'expectedValue' => '%.html',
                'expectedSqlPattern' => '/url LIKE :dcValue\d+/',
                'scenario' => 'ends with file extension'
            ],
        ];
    }

    /**
     * Test multiple operator support detection for complex configurations
     * 
     * @dataProvider operatorSupportMatrix
     */
    public function testOperatorSupportMatrix(
        string $operatorType,
        mixed $config,
        bool $expectedSupport,
        string $scenario
    ): void {
        $operator = $this->operators[$operatorType];
        $result = $operator->supports($config);
        
        $this->assertEquals($expectedSupport, $result, 
            "Support detection failed for {$operatorType} with scenario: {$scenario}");
    }

    public static function operatorSupportMatrix(): array
    {
        return [
            // Cross-operator support testing
            'equal supports scalar' => ['equal', 'test', true, 'equal with scalar'],
            'equal supports eq array' => ['equal', ['eq' => 'test'], true, 'equal with eq array'],
            'equal rejects contains' => ['equal', ['contains' => 'test'], false, 'equal rejects contains'],
            'equal rejects list array' => ['equal', ['a', 'b'], false, 'equal rejects list'],
            
            'arrayIn supports list' => ['arrayIn', ['a', 'b'], true, 'arrayIn with list'],
            'arrayIn rejects scalar' => ['arrayIn', 'test', false, 'arrayIn rejects scalar'],
            'arrayIn rejects assoc' => ['arrayIn', ['key' => 'value'], false, 'arrayIn rejects associative'],
            
            'stringContains supports contains' => ['stringContains', ['contains' => 'test'], true, 'contains with array'],
            'stringContains rejects scalar' => ['stringContains', 'test', false, 'contains rejects scalar'],
            'stringContains rejects other ops' => ['stringContains', ['starts' => 'test'], false, 'contains rejects starts'],
            
            'not supports not key' => ['not', ['not' => 'anything'], true, 'not with any value'],
            'not rejects without key' => ['not', ['other' => 'value'], false, 'not without not key'],
            'not rejects scalar' => ['not', 'test', false, 'not rejects scalar'],
            
            // Edge cases
            'empty array handling' => ['equal', [], false, 'empty array to equal'],
            'null handling' => ['equal', null, true, 'null to equal'],
            'boolean handling' => ['arrayIn', true, false, 'boolean to arrayIn'],
        ];
    }

    /**
     * Test NOT operator wrapping behavior with various nested configurations
     */
    public function testNotOperatorWrappingBehavior(): void
    {
        $notOperator = $this->operators['not'];
        $fieldName = 'test_field';
        
        // Test wrapping different operator types
        $testCases = [
            ['not' => ['eq' => 'test']],
            ['not' => ['contains' => 'search']],
            ['not' => ['active', 'pending']],
            ['not' => ['gte' => 50]],
            ['not' => ['between' => [1, 10]]],
        ];
        
        foreach ($testCases as $config) {
            $callbackCalled = false;
            $receivedConfig = null;
            $receivedHistory = null;
            
            $callback = function($field, $cfg, $qb, $history) use (&$callbackCalled, &$receivedConfig, &$receivedHistory) {
                $callbackCalled = true;
                $receivedConfig = $cfg;
                $receivedHistory = $history;
                return ['wrapped_result'];
            };
            
            $result = $notOperator->wrap($fieldName, $config, $this->queryBuilder, null, $callback);
            
            $this->assertTrue($callbackCalled, 'Callback should be called for wrapping');
            $this->assertEquals($config['not'], $receivedConfig, 'Should pass NOT content to callback');
            $this->assertTrue($receivedHistory->hasOperatorTypeInHistory(NotOperator::class), 
                'History should contain NotOperator');
            $this->assertEquals(['wrapped_result'], $result, 'Should return callback result');
            
            // Reset for next iteration
            $callbackCalled = false;
            $receivedConfig = null;
            $receivedHistory = null;
        }
    }

    /**
     * Test performance with complex nested structures
     */
    public function testPerformanceWithComplexNesting(): void
    {
        // Create a deeply nested NOT structure
        $complexConfig = [
            'not' => [
                'not' => [
                    'not' => [
                        'contains' => 'deep_nesting_test'
                    ]
                ]
            ]
        ];
        
        $fieldName = 'performance_field';
        $nestingLevel = 0;
        
        $callback = function($field, $config, $qb, $history) use (&$nestingLevel) {
            $nestingLevel++;
            // Simulate processing - in real implementation this would continue the chain
            return ["level_{$nestingLevel}"];
        };
        
        $startTime = microtime(true);
        $result = $this->operators['not']->wrap($fieldName, $complexConfig, $this->queryBuilder, null, $callback);
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $this->assertLessThan(10, $executionTime, 'Complex nesting should execute within 10ms');
        $this->assertEquals(['level_1'], $result, 'Should return first level result');
        $this->assertEquals(1, $nestingLevel, 'Should call callback once for first level');
    }
}