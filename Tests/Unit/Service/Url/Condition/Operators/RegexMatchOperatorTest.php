<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\RegexMatchOperator;
use TYPO3\CMS\Core\Database\Connection;

/**
 * Comprehensive test suite for RegexMatchOperator
 * Tests regex pattern matching with database platform-specific operators
 * 
 * Based on config syntax: email: { match: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$" }
 */
final class RegexMatchOperatorTest extends BaseOperatorTest
{
    private RegexMatchOperator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = new RegexMatchOperator();
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
            // Arrays with 'match' key (should be supported)
            'match with email pattern' => [
                ['match' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$'], 
                true, 
                'email regex pattern'
            ],
            'match with phone pattern' => [
                ['match' => '^\\+?[1-9]\\d{1,14}$'], 
                true, 
                'phone regex pattern'
            ],
            'match with simple pattern' => [
                ['match' => '[0-9]+'], 
                true, 
                'simple numeric pattern'
            ],
            'match with empty pattern' => [
                ['match' => ''], 
                true, 
                'empty regex pattern'
            ],
            'match with complex pattern' => [
                ['match' => '(?i)^(admin|user)_[a-z]+$'], 
                true, 
                'complex pattern with flags'
            ],
            'complex array with match' => [
                ['match' => 'test', 'other' => 'ignored'], 
                true, 
                'complex array with match'
            ],
            
            // Arrays without 'match' key (should not be supported)
            'empty array' => [[], false, 'empty array'],
            'array without match' => [['contains' => 'test'], false, 'array with other operators'],
            'list array' => [['active', 'pending'], false, 'sequential array'],
            'complex array without match' => [['not' => ['eq' => 'test']], false, 'nested without match'],
            
            // Scalar values (should not be supported - match requires array syntax)
            'scalar string' => ['test', false, 'scalar string'],
            'scalar regex' => ['^[a-z]+$', false, 'scalar regex pattern'],
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
        string $expectedPattern,
        string $scenario
    ): void {
        $fieldName = 'test_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        // Mock database platform for consistent testing
        $connection = $this->createMock(Connection::class);
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getName')->willReturn('mysql');
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $this->queryBuilder->method('getConnection')->willReturn($connection);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify SQL expression format
        $regexOp = $hasNotInHistory ? 'NOT REGEXP' : 'REGEXP';
        $expectedRegex = "/^{$fieldName} {$regexOp} :dcValue\\d+$/";
        $this->assertSqlExpression($expectedRegex, $result);
        
        // Verify parameter creation
        $this->assertParameterCreated($expectedPattern);
    }

    public static function processDataProvider(): array
    {
        $scenarios = [];
        
        // Test various regex patterns with and without NOT operator
        $regexPatterns = [
            'email pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$',
            'phone pattern' => '^\\+?[1-9]\\d{1,14}$',
            'simple digits' => '[0-9]+',
            'word boundaries' => '\\btest\\b',
            'case insensitive' => '(?i)admin',
            'complex pattern' => '^(admin|user)_[a-zA-Z0-9]{3,}$',
            'empty pattern' => '',
            'single char' => 'a',
            'escaped special chars' => '\\$\\^\\*\\+\\?\\.',
            'unicode pattern' => '[\\u00C0-\\u017F]+',
        ];
        
        foreach ($regexPatterns as $patternKey => $pattern) {
            // Without NOT operator
            $scenarios["{$patternKey} without NOT"] = [
                'fieldConfig' => ['match' => $pattern],
                'hasNotInHistory' => false,
                'expectedPattern' => $pattern,
                'scenario' => "{$patternKey} normal regex match",
            ];
            
            // With NOT operator
            $scenarios["{$patternKey} with NOT"] = [
                'fieldConfig' => ['match' => $pattern],
                'hasNotInHistory' => true,
                'expectedPattern' => $pattern,
                'scenario' => "{$patternKey} negated regex match",
            ];
        }
        
        return $scenarios;
    }

    /**
     * @dataProvider databasePlatformProvider
     */
    public function testDatabasePlatformSpecificOperators(
        string $platformName,
        string $expectedRegexOp,
        string $expectedNotRegexOp,
        string $scenario
    ): void {
        $fieldName = 'platform_field';
        $fieldConfig = ['match' => 'test'];
        
        // Mock database platform
        $connection = $this->createMock(Connection::class);
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getName')->willReturn($platformName);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $this->queryBuilder->method('getConnection')->willReturn($connection);
        
        // Test without NOT
        $historyNormal = $this->createOperatorHistory(false);
        $resultNormal = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $historyNormal);
        $this->assertStringContainsString($expectedRegexOp, $resultNormal, 
            "Normal regex operator incorrect for {$scenario}");
        
        // Test with NOT
        $historyNot = $this->createOperatorHistory(true);
        $resultNot = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $historyNot);
        $this->assertStringContainsString($expectedNotRegexOp, $resultNot, 
            "Negated regex operator incorrect for {$scenario}");
    }

    public static function databasePlatformProvider(): array
    {
        return [
            'MySQL platform' => ['mysql', 'REGEXP', 'NOT REGEXP', 'MySQL database'],
            'MariaDB platform' => ['mariadb', 'REGEXP', 'NOT REGEXP', 'MariaDB database'],
            'PostgreSQL platform' => ['postgresql', '~', '!~', 'PostgreSQL database'],
            'PostgreSQL PDO platform' => ['pdo_postgresql', '~', '!~', 'PostgreSQL via PDO'],
            'SQLite platform' => ['sqlite', 'REGEXP', 'NOT REGEXP', 'SQLite database'],
            'SQLite3 platform' => ['sqlite3', 'REGEXP', 'NOT REGEXP', 'SQLite3 database'],
            'SQLite PDO platform' => ['pdo_sqlite', 'REGEXP', 'NOT REGEXP', 'SQLite via PDO'],
            'Unknown platform' => ['unknown_db', 'REGEXP', 'NOT REGEXP', 'fallback to default'],
        ];
    }

    /**
     * @dataProvider fieldNameVariationsProvider
     */
    public function testProcessWithVariousFieldNames(string $fieldName): void
    {
        $fieldConfig = ['match' => '[0-9]+'];
        $history = $this->createOperatorHistory(false);
        
        // Mock database platform
        $connection = $this->createMock(Connection::class);
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getName')->willReturn('mysql');
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $this->queryBuilder->method('getConnection')->willReturn($connection);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify field name is preserved in expression
        $this->assertStringContainsString($fieldName, $result, 
            "Field name '{$fieldName}' not found in expression");
        $this->assertStringContainsString('REGEXP', $result, 
            "REGEXP operator not found in expression");
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
        
        // Mock database platform
        $connection = $this->createMock(Connection::class);
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getName')->willReturn('mysql');
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $this->queryBuilder->method('getConnection')->willReturn($connection);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertIsString($result, "Process should return string expression");
        $this->assertNotEmpty($result, "Expression should not be empty");
        
        // Verify operator type based on NOT history
        $expectedOp = $hasNotInHistory ? 'NOT REGEXP' : 'REGEXP';
        $this->assertStringContainsString($expectedOp, $result, 
            "Expected operator '{$expectedOp}' not found for scenario: {$scenario}");
    }

    public static function edgeCaseDataProvider(): array
    {
        return [
            // Special regex patterns
            'empty pattern without NOT' => [['match' => ''], false, 'matches empty pattern', 'empty pattern'],
            'empty pattern with NOT' => [['match' => ''], true, 'negated empty pattern', 'empty pattern negated'],
            'single char pattern' => [['match' => 'a'], false, 'matches single char', 'minimal pattern'],
            'wildcard pattern' => [['match' => '.*'], false, 'matches everything', 'wildcard pattern'],
            
            // Complex patterns
            'email regex' => [
                ['match' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$'], 
                false, 
                'matches email format', 
                'complex email pattern'
            ],
            'phone regex' => [
                ['match' => '^\\+?[1-9]\\d{1,14}$'], 
                true, 
                'negated phone format', 
                'international phone pattern'
            ],
            
            // Special characters and escaping
            'escaped special chars' => [
                ['match' => '\\$\\^\\*\\+\\?\\(\\)\\[\\]\\{\\}'], 
                false, 
                'matches escaped specials', 
                'escaped special characters'
            ],
            
            // Multi-key array
            'multi-key array' => [
                ['match' => 'test', 'ignored' => 'value'], 
                false, 
                'uses match value only', 
                'multi-key array'
            ],
        ];
    }

    /**
     * Test behavior with null history
     */
    public function testProcessWithNullHistory(): void
    {
        $fieldName = 'null_history_field';
        $fieldConfig = ['match' => '[0-9]+'];
        
        // Mock database platform
        $connection = $this->createMock(Connection::class);
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getName')->willReturn('mysql');
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $this->queryBuilder->method('getConnection')->willReturn($connection);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, null);
        
        // Should behave as normal regex when no history
        $this->assertStringContainsString('REGEXP', $result);
        $this->assertStringNotContainsString('NOT REGEXP', $result);
    }

    /**
     * Test that operator correctly extracts pattern from array config
     */
    public function testArrayConfigValueExtraction(): void
    {
        $fieldName = 'extraction_field';
        $testPattern = 'extracted_pattern';
        $fieldConfig = ['match' => $testPattern, 'other_key' => 'should_be_ignored'];
        $history = $this->createOperatorHistory(false);
        
        // Mock database platform
        $connection = $this->createMock(Connection::class);
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getName')->willReturn('mysql');
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $this->queryBuilder->method('getConnection')->willReturn($connection);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify that only the 'match' value was used for parameter
        $this->assertParameterCreated($testPattern);
    }

    /**
     * Test platform-specific operator selection consistency
     * 
     * @dataProvider platformConsistencyProvider
     */
    public function testPlatformOperatorConsistency(
        string $platformName,
        bool $hasNot,
        string $expectedOperator,
        string $scenario
    ): void {
        $fieldName = 'consistency_field';
        $fieldConfig = ['match' => 'test_pattern'];
        $history = $this->createOperatorHistory($hasNot);
        
        // Mock database platform
        $connection = $this->createMock(Connection::class);
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getName')->willReturn($platformName);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $this->queryBuilder->method('getConnection')->willReturn($connection);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertStringContainsString($expectedOperator, $result, 
            "Platform operator consistency failed for scenario: {$scenario}");
    }

    public static function platformConsistencyProvider(): array
    {
        return [
            // MySQL/MariaDB consistency
            'MySQL normal' => ['mysql', false, 'REGEXP', 'MySQL normal regex'],
            'MySQL negated' => ['mysql', true, 'NOT REGEXP', 'MySQL negated regex'],
            'MariaDB normal' => ['mariadb', false, 'REGEXP', 'MariaDB normal regex'],
            'MariaDB negated' => ['mariadb', true, 'NOT REGEXP', 'MariaDB negated regex'],
            
            // PostgreSQL consistency
            'PostgreSQL normal' => ['postgresql', false, '~', 'PostgreSQL normal regex'],
            'PostgreSQL negated' => ['postgresql', true, '!~', 'PostgreSQL negated regex'],
            'PostgreSQL PDO normal' => ['pdo_postgresql', false, '~', 'PostgreSQL PDO normal regex'],
            'PostgreSQL PDO negated' => ['pdo_postgresql', true, '!~', 'PostgreSQL PDO negated regex'],
            
            // SQLite consistency
            'SQLite normal' => ['sqlite', false, 'REGEXP', 'SQLite normal regex'],
            'SQLite negated' => ['sqlite', true, 'NOT REGEXP', 'SQLite negated regex'],
            'SQLite3 normal' => ['sqlite3', false, 'REGEXP', 'SQLite3 normal regex'],
            'SQLite3 negated' => ['sqlite3', true, 'NOT REGEXP', 'SQLite3 negated regex'],
            
            // Default fallback
            'Unknown normal' => ['unknown', false, 'REGEXP', 'Unknown platform fallback normal'],
            'Unknown negated' => ['unknown', true, 'NOT REGEXP', 'Unknown platform fallback negated'],
        ];
    }
}