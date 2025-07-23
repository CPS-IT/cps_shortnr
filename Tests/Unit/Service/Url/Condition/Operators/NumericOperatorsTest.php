<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\BetweenOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\GreaterOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\IssetOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\LessOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\RegexMatchOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\StringEndsOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\StringStartsOperator;
use TYPO3\CMS\Core\Database\Connection;

/**
 * Comprehensive test suite for numeric and specialized operators
 * Tests BetweenOperator, GreaterOperator, LessOperator, IssetOperator, RegexMatchOperator,
 * StringStartsOperator, and StringEndsOperator
 */
final class NumericOperatorsTest extends BaseOperatorTest
{
    private BetweenOperator $betweenOperator;
    private GreaterOperator $greaterOperator;
    private LessOperator $lessOperator;
    private IssetOperator $issetOperator;
    private RegexMatchOperator $regexOperator;
    private StringStartsOperator $startsOperator;
    private StringEndsOperator $endsOperator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->betweenOperator = new BetweenOperator();
        $this->greaterOperator = new GreaterOperator();
        $this->lessOperator = new LessOperator();
        $this->issetOperator = new IssetOperator();
        $this->regexOperator = new RegexMatchOperator();
        $this->startsOperator = new StringStartsOperator();
        $this->endsOperator = new StringEndsOperator();
    }

    /**
     * @dataProvider betweenOperatorDataProvider
     */
    public function testBetweenOperator(
        mixed $fieldConfig,
        bool $shouldSupport,
        ?array $expectedRange,
        bool $hasNotInHistory,
        string $expectedOperator,
        string $scenario
    ): void {
        // Test support
        $this->assertEquals($shouldSupport, $this->betweenOperator->support($fieldConfig),
            "Between operator support failed for: {$scenario}");
        
        if (!$shouldSupport) {
            return; // Skip processing test if not supported
        }
        
        $fieldName = 'between_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->betweenOperator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $expectedPattern = "/^{$fieldName} {$expectedOperator} :dcValue\\d+ AND :dcValue\\d+$/";
        $this->assertSqlExpression($expectedPattern, $result);
        
        if ($expectedRange) {
            // First parameter should be min value
            $this->assertParameterCreated($expectedRange[0]);
        }
    }

    public static function betweenOperatorDataProvider(): array
    {
        return [
            // Supported cases
            'integer range normal' => [
                ['between' => [10, 50]], true, [10, 50], false, 'BETWEEN', 'integer range'
            ],
            'integer range with NOT' => [
                ['between' => [10, 50]], true, [10, 50], true, 'NOT BETWEEN', 'negated integer range'
            ],
            'float range' => [
                ['between' => [1.5, 9.8]], true, [1.5, 9.8], false, 'BETWEEN', 'float range'
            ],
            'string range' => [
                ['between' => ['A', 'Z']], true, ['A', 'Z'], false, 'BETWEEN', 'string range'
            ],
            'reverse order' => [
                ['between' => [100, 1]], true, [100, 1], false, 'BETWEEN', 'reverse order range'
            ],
            'same values' => [
                ['between' => [5, 5]], true, [5, 5], true, 'NOT BETWEEN', 'same value range negated'
            ],
            
            // Unsupported cases
            'no between key' => [['eq' => 5], false, null, false, '', 'missing between key'],
            'scalar value' => [42, false, null, false, '', 'scalar integer'],
            'empty array' => [[], false, null, false, '', 'empty array'],
            'single value array' => [['between' => [5]], false, null, false, '', 'incomplete range'],
            'too many values' => [['between' => [1, 2, 3]], false, null, false, '', 'too many range values'],
        ];
    }

    /**
     * @dataProvider greaterOperatorDataProvider
     */
    public function testGreaterOperator(
        mixed $fieldConfig,
        bool $shouldSupport,
        mixed $expectedValue,
        bool $hasNotInHistory,
        string $expectedOperator,
        string $scenario
    ): void {
        $this->assertEquals($shouldSupport, $this->greaterOperator->support($fieldConfig),
            "Greater operator support failed for: {$scenario}");
        
        if (!$shouldSupport) return;
        
        $fieldName = 'greater_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->greaterOperator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $expectedPattern = "/^{$fieldName} {$expectedOperator} :dcValue\\d+$/";
        $this->assertSqlExpression($expectedPattern, $result);
        $this->assertParameterCreated($expectedValue);
    }

    public static function greaterOperatorDataProvider(): array
    {
        return [
            // Greater than (gt)
            'gt integer' => [['gt' => 50], true, 50, false, '>', 'greater than integer'],
            'gt with NOT' => [['gt' => 50], true, 50, true, '<=', 'NOT greater than becomes less or equal'],
            'gt float' => [['gt' => 3.14], true, 3.14, false, '>', 'greater than float'],
            'gt string' => [['gt' => 'M'], true, 'M', false, '>', 'greater than string'],
            
            // Greater than or equal (gte)
            'gte integer' => [['gte' => 18], true, 18, false, '>=', 'greater than or equal integer'],
            'gte with NOT' => [['gte' => 18], true, 18, true, '<', 'NOT greater or equal becomes less than'],
            'gte zero' => [['gte' => 0], true, 0, false, '>=', 'greater than or equal zero'],
            'gte negative' => [['gte' => -10], true, -10, true, '<', 'NOT gte negative number'],
            
            // Unsupported cases
            'no gt/gte key' => [['lt' => 5], false, null, false, '', 'wrong operator key'],
            'scalar value' => [42, false, null, false, '', 'scalar value'],
            'empty array' => [[], false, null, false, '', 'empty array'],
            'multiple keys' => [['gt' => 5, 'gte' => 10], true, 5, false, '>', 'multiple keys uses first supported'],
        ];
    }

    /**
     * @dataProvider lessOperatorDataProvider
     */
    public function testLessOperator(
        mixed $fieldConfig,
        bool $shouldSupport,
        mixed $expectedValue,
        bool $hasNotInHistory,
        string $expectedOperator,
        string $scenario
    ): void {
        $this->assertEquals($shouldSupport, $this->lessOperator->support($fieldConfig),
            "Less operator support failed for: {$scenario}");
        
        if (!$shouldSupport) return;
        
        $fieldName = 'less_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->lessOperator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $expectedPattern = "/^{$fieldName} {$expectedOperator} :dcValue\\d+$/";
        $this->assertSqlExpression($expectedPattern, $result);
        $this->assertParameterCreated($expectedValue);
    }

    public static function lessOperatorDataProvider(): array
    {
        return [
            // Less than (lt)
            'lt integer' => [['lt' => 30], true, 30, false, '<', 'less than integer'],
            'lt with NOT' => [['lt' => 30], true, 30, true, '>=', 'NOT less than becomes greater or equal'],
            'lt float' => [['lt' => 99.99], true, 99.99, false, '<', 'less than float'],
            'lt zero' => [['lt' => 0], true, 0, false, '<', 'less than zero'],
            
            // Less than or equal (lte)
            'lte integer' => [['lte' => 100], true, 100, false, '<=', 'less than or equal integer'],
            'lte with NOT' => [['lte' => 100], true, 100, true, '>', 'NOT less or equal becomes greater than'],
            'lte negative' => [['lte' => -5], true, -5, false, '<=', 'less than or equal negative'],
            'lte string' => [['lte' => 'Z'], true, 'Z', true, '>', 'NOT lte string'],
            
            // Unsupported cases
            'no lt/lte key' => [['gt' => 5], false, null, false, '', 'wrong operator key'],
            'scalar value' => [42, false, null, false, '', 'scalar value'],
            'empty array' => [[], false, null, false, '', 'empty array'],
        ];
    }

    /**
     * @dataProvider issetOperatorDataProvider
     */
    public function testIssetOperator(
        mixed $fieldConfig,
        bool $shouldSupport,
        bool $hasNotInHistory,
        string $expectedOperator,
        string $scenario
    ): void {
        $this->assertEquals($shouldSupport, $this->issetOperator->support($fieldConfig),
            "Isset operator support failed for: {$scenario}");
        
        if (!$shouldSupport) return;
        
        $fieldName = 'isset_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->issetOperator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $expectedPattern = "/^{$fieldName} {$expectedOperator}$/";
        $this->assertSqlExpression($expectedPattern, $result);
        
        // Isset operator shouldn't create parameters
        $parameters = $this->queryBuilderHelper->getCreatedParameters();
        $this->assertEmpty($parameters, "Isset operator should not create parameters");
    }

    public static function issetOperatorDataProvider(): array
    {
        return [
            'isset true' => [['isset' => true], true, false, 'IS NOT NULL', 'isset true checks not null'],
            'isset true with NOT' => [['isset' => true], true, true, 'IS NULL', 'NOT isset becomes null check'],
            'isset false' => [['isset' => false], true, false, 'IS NULL', 'isset false checks null'],
            'isset false with NOT' => [['isset' => false], true, true, 'IS NOT NULL', 'NOT isset false becomes not null'],
            'isset with string' => [['isset' => 'yes'], true, false, 'IS NOT NULL', 'truthy string as isset'],
            'isset with zero' => [['isset' => 0], true, false, 'IS NULL', 'falsy zero as isset'],
            
            // Unsupported cases
            'no isset key' => [['eq' => true], false, false, '', 'missing isset key'],
            'scalar boolean' => [true, false, false, '', 'scalar boolean'],
            'empty array' => [[], false, false, '', 'empty array'],
        ];
    }

    /**
     * @dataProvider stringStartsOperatorDataProvider
     */
    public function testStringStartsOperator(
        mixed $fieldConfig,
        bool $shouldSupport,
        ?string $expectedValue,
        bool $hasNotInHistory,
        string $expectedOperator,
        string $scenario
    ): void {
        $this->assertEquals($shouldSupport, $this->startsOperator->support($fieldConfig),
            "String starts operator support failed for: {$scenario}");
        
        if (!$shouldSupport) return;
        
        $fieldName = 'starts_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->startsOperator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $expectedPattern = "/^{$fieldName} {$expectedOperator} :dcValue\\d+$/";
        $this->assertSqlExpression($expectedPattern, $result);
        
        if ($expectedValue !== null) {
            $this->assertParameterCreated($expectedValue . '%');
        }
    }

    public static function stringStartsOperatorDataProvider(): array
    {
        return [
            'starts simple' => [['starts' => 'hello'], true, 'hello', false, 'LIKE', 'simple starts with'],
            'starts with NOT' => [['starts' => 'admin'], true, 'admin', true, 'NOT LIKE', 'NOT starts with'],
            'starts empty' => [['starts' => ''], true, '', false, 'LIKE', 'starts with empty string'],
            'starts with wildcards' => [['starts' => 'test%_'], true, 'test\\%\\_', false, 'LIKE', 'starts with wildcards escaped'],
            'starts with number' => [['starts' => '123'], true, '123', false, 'LIKE', 'starts with numeric string'],
            'starts with special' => [['starts' => '@#$'], true, '@#$', true, 'NOT LIKE', 'NOT starts with special chars'],
            
            // Unsupported
            'no starts key' => [['ends' => 'test'], false, null, false, '', 'wrong operator key'],
            'scalar value' => ['test', false, null, false, '', 'scalar string'],
            'empty array' => [[], false, null, false, '', 'empty array'],
        ];
    }

    /**
     * @dataProvider stringEndsOperatorDataProvider
     */
    public function testStringEndsOperator(
        mixed $fieldConfig,
        bool $shouldSupport,
        ?string $expectedValue,
        bool $hasNotInHistory,
        string $expectedOperator,
        string $scenario
    ): void {
        $this->assertEquals($shouldSupport, $this->endsOperator->support($fieldConfig),
            "String ends operator support failed for: {$scenario}");
        
        if (!$shouldSupport) return;
        
        $fieldName = 'ends_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->endsOperator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $expectedPattern = "/^{$fieldName} {$expectedOperator} :dcValue\\d+$/";
        $this->assertSqlExpression($expectedPattern, $result);
        
        if ($expectedValue !== null) {
            $this->assertParameterCreated('%' . $expectedValue);
        }
    }

    public static function stringEndsOperatorDataProvider(): array
    {
        return [
            'ends simple' => [['ends' => '.com'], true, '.com', false, 'LIKE', 'simple ends with'],
            'ends with NOT' => [['ends' => '.tmp'], true, '.tmp', true, 'NOT LIKE', 'NOT ends with'],
            'ends empty' => [['ends' => ''], true, '', false, 'LIKE', 'ends with empty string'],
            'ends with wildcards' => [['ends' => 'test%_'], true, 'test\\%\\_', false, 'LIKE', 'ends with wildcards escaped'],
            'ends with path' => [['ends' => '/index.html'], true, '/index.html', false, 'LIKE', 'ends with file path'],
            'ends with extension' => [['ends' => '.pdf'], true, '.pdf', true, 'NOT LIKE', 'NOT ends with extension'],
            
            // Unsupported
            'no ends key' => [['starts' => 'test'], false, null, false, '', 'wrong operator key'],
            'scalar value' => ['test', false, null, false, '', 'scalar string'],
            'empty array' => [[], false, null, false, '', 'empty array'],
        ];
    }

    /**
     * @dataProvider regexOperatorDataProvider
     */
    public function testRegexOperator(
        mixed $fieldConfig,
        bool $shouldSupport,
        ?string $expectedPattern,
        bool $hasNotInHistory,
        string $expectedOperator,
        string $scenario
    ): void {
        $this->assertEquals($shouldSupport, $this->regexOperator->support($fieldConfig),
            "Regex operator support failed for: {$scenario}");
        
        if (!$shouldSupport) return;
        
        $fieldName = 'regex_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->regexOperator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $expectedSqlPattern = "/^{$fieldName} {$expectedOperator} :dcValue\\d+$/";
        $this->assertSqlExpression($expectedSqlPattern, $result);
        
        if ($expectedPattern !== null) {
            $this->assertParameterCreated($expectedPattern);
        }
    }

    public static function regexOperatorDataProvider(): array
    {
        return [
            'email regex' => [
                ['match' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$'],
                true,
                '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$',
                false,
                'REGEXP',
                'email validation regex'
            ],
            'phone regex with NOT' => [
                ['match' => '^\\+?[1-9]\\d{1,14}$'],
                true,
                '^\\+?[1-9]\\d{1,14}$',
                true,
                'NOT REGEXP',
                'NOT phone validation regex'
            ],
            'simple pattern' => [
                ['match' => '[0-9]+'],
                true,
                '[0-9]+',
                false,
                'REGEXP',
                'simple number pattern'
            ],
            'complex pattern' => [
                ['match' => '(?i)^(admin|user)_[a-z]+$'],
                true,
                '(?i)^(admin|user)_[a-z]+$',
                true,
                'NOT REGEXP',
                'complex pattern with flags'
            ],
            'empty pattern' => [
                ['match' => ''],
                true,
                '',
                false,
                'REGEXP',
                'empty regex pattern'
            ],
            
            // Unsupported
            'no match key' => [['regex' => 'test'], false, null, false, '', 'wrong operator key'],
            'scalar pattern' => ['[0-9]+', false, null, false, '', 'scalar regex'],
            'empty array' => [[], false, null, false, '', 'empty array'],
        ];
    }

    /**
     * Test parameter types for numeric operators
     * 
     * @dataProvider parameterTypeTestProvider
     */
    public function testParameterTypes(
        string $operatorType,
        mixed $config,
        mixed $expectedValue,
        int $expectedType,
        string $scenario
    ): void {
        $operator = match($operatorType) {
            'greater' => $this->greaterOperator,
            'less' => $this->lessOperator,
            'between' => $this->betweenOperator,
            'regex' => $this->regexOperator,
            'starts' => $this->startsOperator,
            'ends' => $this->endsOperator,
        };
        
        $fieldName = 'type_test_field';
        $history = $this->createOperatorHistory(false);
        
        $operator->process($fieldName, $config, $this->queryBuilder, $history);
        
        $this->assertParameterCreated($expectedValue, $expectedType);
    }

    public static function parameterTypeTestProvider(): array
    {
        return [
            'greater integer' => [
                'greater', ['gt' => 42], 42, Connection::PARAM_INT, 'integer parameter'
            ],
            'greater string' => [
                'greater', ['gte' => 'test'], 'test', Connection::PARAM_STR, 'string parameter'
            ],
            'less float' => [
                'less', ['lt' => 3.14], 3.14, Connection::PARAM_STR, 'float as string parameter'
            ],
            'between first param' => [
                'between', ['between' => [10, 20]], 10, Connection::PARAM_INT, 'between first integer'
            ],
            'regex pattern' => [
                'regex', ['match' => '[0-9]+'], '[0-9]+', Connection::PARAM_STR, 'regex pattern string'
            ],
            'starts prefix' => [
                'starts', ['starts' => 'prefix'], 'prefix%', Connection::PARAM_STR, 'starts with pattern'
            ],
            'ends suffix' => [
                'ends', ['ends' => 'suffix'], '%suffix', Connection::PARAM_STR, 'ends with pattern'
            ],
        ];
    }

    /**
     * Test edge cases across all numeric operators
     */
    public function testNumericOperatorEdgeCases(): void
    {
        // Test zero values
        $result1 = $this->greaterOperator->process('field', ['gt' => 0], $this->queryBuilder, null);
        $this->assertStringContainsString('> :dcValue', $result1);
        $this->assertParameterCreated(0);
        
        // Reset for next test
        $this->queryBuilderHelper->reset();
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
        
        // Test negative values
        $result2 = $this->lessOperator->process('field', ['lt' => -10], $this->queryBuilder, null);
        $this->assertStringContainsString('< :dcValue', $result2);
        $this->assertParameterCreated(-10);
        
        // Reset for next test
        $this->queryBuilderHelper->reset();
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
        
        // Test between with same values
        $result3 = $this->betweenOperator->process('field', ['between' => [5, 5]], $this->queryBuilder, null);
        $this->assertStringContainsString('BETWEEN', $result3);
        $this->assertParameterCreated(5);
    }
}