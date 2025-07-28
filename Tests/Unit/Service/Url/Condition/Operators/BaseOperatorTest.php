<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistory;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\NotOperator;
use CPSIT\ShortNr\Service\Url\Condition\Operators\OperatorInterface;
use CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators\Helper\QueryBuilderMockHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Base class for operator testing with standardized patterns
 * Provides common test data providers and helper methods
 */
abstract class BaseOperatorTest extends TestCase
{
    protected QueryBuilderMockHelper $queryBuilderHelper;
    protected QueryBuilder&MockObject $queryBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryBuilderHelper = new QueryBuilderMockHelper($this);
        $this->queryBuilder = $this->queryBuilderHelper->createQueryBuilderMock();
    }

    protected function tearDown(): void
    {
        $this->queryBuilderHelper->reset();
        parent::tearDown();
    }

    /**
     * Creates an operator history mock with optional NotOperator
     */
    protected function createOperatorHistory(bool $hasNotOperator = false): OperatorHistoryInterface&MockObject
    {
        $history = $this->createMock(OperatorHistoryInterface::class);
        $history->method('hasOperatorTypeInHistory')
            ->willReturnCallback(function(string $className) use ($hasNotOperator): bool {
                return $hasNotOperator && $className === NotOperator::class;
            });
        return $history;
    }

    /**
     * Data provider for field names - tests various database field naming conventions
     */
    public static function fieldNameProvider(): array
    {
        return [
            'simple field' => ['uid'],
            'underscore field' => ['sys_language_uid'],
            'camelCase field' => ['firstName'],
            'table.field notation' => ['pages.uid'],
            'complex field name' => ['tx_extension_domain_model_table'],
        ];
    }

    /**
     * Data provider for NOT operator scenarios
     */
    public static function operatorHistoryProvider(): array
    {
        return [
            'no history (null)' => [null, false],
            'history without NOT' => ['history_without_not', false],
            'history with NOT' => ['history_with_not', true],
        ];
    }

    /**
     * Data provider combining field names with operator history
     */
    public static function fieldNameWithHistoryProvider(): array
    {
        $fieldNames = self::fieldNameProvider();
        $histories = self::operatorHistoryProvider();
        $combinations = [];

        foreach ($fieldNames as $fieldKey => $fieldData) {
            foreach ($histories as $historyKey => $historyData) {
                $key = "{$fieldKey} + {$historyKey}";
                $combinations[$key] = [
                    'fieldName' => $fieldData[0],
                    'historyData' => $historyData,
                ];
            }
        }

        return $combinations;
    }

    /**
     * Creates test scenarios for specific operator with various value types
     */
    protected function createOperatorScenarios(string $operatorKey, array $testValues): array
    {
        $scenarios = [];
        
        foreach ($testValues as $valueKey => $valueData) {
            foreach (self::operatorHistoryProvider() as $historyKey => $historyData) {
                $key = "{$valueKey} + {$historyKey}";
                $scenarios[$key] = [
                    'fieldConfig' => [$operatorKey => $valueData['value']],
                    'expectedValue' => $valueData['value'],
                    'expectedType' => $valueData['type'] ?? null,
                    'historyHasNot' => $historyData[1],
                    'expectedOperation' => $historyData[1] ? $valueData['negatedOp'] : $valueData['normalOp'],
                ];
            }
        }

        return $scenarios;
    }

    /**
     * Common data types for testing parameter creation
     */
    public static function commonDataTypes(): array
    {
        return [
            'integer' => [
                'value' => 42,
                'type' => \TYPO3\CMS\Core\Database\Connection::PARAM_INT,
                'normalOp' => 'normal',
                'negatedOp' => 'negated',
            ],
            'string' => [
                'value' => 'test',
                'type' => \TYPO3\CMS\Core\Database\Connection::PARAM_INT_ARRAY,
                'normalOp' => 'normal',
                'negatedOp' => 'negated',
            ],
            'boolean true' => [
                'value' => true,
                'type' => \TYPO3\CMS\Core\Database\Connection::PARAM_BOOL,
                'normalOp' => 'normal',
                'negatedOp' => 'negated',
            ],
            'boolean false' => [
                'value' => false,
                'type' => \TYPO3\CMS\Core\Database\Connection::PARAM_BOOL,
                'normalOp' => 'normal',
                'negatedOp' => 'negated',
            ],
            'null' => [
                'value' => null,
                'type' => \TYPO3\CMS\Core\Database\Connection::PARAM_NULL,
                'normalOp' => 'normal',
                'negatedOp' => 'negated',
            ],
            'float as string' => [
                'value' => '3.14',
                'type' => \TYPO3\CMS\Core\Database\Connection::PARAM_INT_ARRAY,
                'normalOp' => 'normal',
                'negatedOp' => 'negated',
            ],
        ];
    }

    /**
     * Array data types for array operators
     */
    public static function arrayDataTypes(): array
    {
        return [
            'integer array' => [
                'value' => [1, 2, 3],
                'type' => \Doctrine\DBAL\ArrayParameterType::INTEGER,
                'normalOp' => 'IN',
                'negatedOp' => 'NOT IN',
            ],
            'string array' => [
                'value' => ['active', 'pending', 'draft'],
                'type' => \Doctrine\DBAL\ArrayParameterType::STRING,
                'normalOp' => 'IN',
                'negatedOp' => 'NOT IN',
            ],
            'single string array' => [
                'value' => ['single'],
                'type' => \Doctrine\DBAL\ArrayParameterType::STRING,
                'normalOp' => 'IN',
                'negatedOp' => 'NOT IN',
            ],
            'mixed type array' => [
                'value' => [1, 'two', 3],
                'type' => \Doctrine\DBAL\ArrayParameterType::STRING,
                'normalOp' => 'IN',
                'negatedOp' => 'NOT IN',
            ],
            'empty array' => [
                'value' => [],
                'type' => \Doctrine\DBAL\ArrayParameterType::STRING,
                'normalOp' => 'IN',
                'negatedOp' => 'NOT IN',
            ],
        ];
    }

    /**
     * String pattern data for string operators
     */
    public static function stringPatternData(): array
    {
        return [
            'simple string' => [
                'value' => 'test',
                'normalOp' => 'LIKE',
                'negatedOp' => 'NOT LIKE',
            ],
            'string with spaces' => [
                'value' => 'hello world',
                'normalOp' => 'LIKE',
                'negatedOp' => 'NOT LIKE',
            ],
            'string with special chars' => [
                'value' => 'test@domain.com',
                'normalOp' => 'LIKE',
                'negatedOp' => 'NOT LIKE',
            ],
            'string with wildcards' => [
                'value' => 'test%_pattern',
                'normalOp' => 'LIKE',
                'negatedOp' => 'NOT LIKE',
            ],
            'empty string' => [
                'value' => '',
                'normalOp' => 'LIKE',
                'negatedOp' => 'NOT LIKE',
            ],
        ];
    }

    /**
     * Numeric comparison data for range operators
     */
    public static function numericComparisonData(): array
    {
        return [
            'positive integer' => [
                'value' => 100,
                'type' => \TYPO3\CMS\Core\Database\Connection::PARAM_INT,
            ],
            'zero' => [
                'value' => 0,
                'type' => \TYPO3\CMS\Core\Database\Connection::PARAM_INT,
            ],
            'negative integer' => [
                'value' => -50,
                'type' => \TYPO3\CMS\Core\Database\Connection::PARAM_INT,
            ],
            'string number' => [
                'value' => '42',
                'type' => \TYPO3\CMS\Core\Database\Connection::PARAM_STR,
            ],
            'float string' => [
                'value' => '3.14159',
                'type' => \TYPO3\CMS\Core\Database\Connection::PARAM_STR,
            ],
        ];
    }

    /**
     * Between operator range data
     */
    public static function betweenRangeData(): array
    {
        return [
            'integer range' => [
                'value' => [18, 65],
                'expectedMin' => 18,
                'expectedMax' => 65,
                'normalOp' => 'BETWEEN',
                'negatedOp' => 'NOT BETWEEN',
            ],
            'string range' => [
                'value' => ['A', 'Z'],
                'expectedMin' => 'A',
                'expectedMax' => 'Z',
                'normalOp' => 'BETWEEN',
                'negatedOp' => 'NOT BETWEEN',
            ],
            'same values' => [
                'value' => [5, 5],
                'expectedMin' => 5,
                'expectedMax' => 5,
                'normalOp' => 'BETWEEN',
                'negatedOp' => 'NOT BETWEEN',
            ],
            'reverse order' => [
                'value' => [100, 1], // Should handle gracefully
                'expectedMin' => 100,
                'expectedMax' => 1,
                'normalOp' => 'BETWEEN',
                'negatedOp' => 'NOT BETWEEN',
            ],
        ];
    }

    /**
     * Regex pattern data for regex operators
     */
    public static function regexPatternData(): array
    {
        return [
            'email pattern' => [
                'value' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$',
                'normalOp' => 'REGEXP',
                'negatedOp' => 'NOT REGEXP',
            ],
            'phone pattern' => [
                'value' => '^\\+?[1-9]\\d{1,14}$',
                'normalOp' => 'REGEXP',
                'negatedOp' => 'NOT REGEXP',
            ],
            'simple pattern' => [
                'value' => '[0-9]+',
                'normalOp' => 'REGEXP',
                'negatedOp' => 'NOT REGEXP',
            ],
            'complex pattern with flags' => [
                'value' => '(?i)^(admin|user)_[a-z]+$',
                'normalOp' => 'REGEXP',
                'negatedOp' => 'NOT REGEXP',
            ],
        ];
    }

    /**
     * Assert that SQL expression was created correctly
     */
    protected function assertSqlExpression(string $expectedPattern, string $actualExpression): void
    {
        $this->assertMatchesRegularExpression($expectedPattern, $actualExpression, 
            "SQL expression does not match expected pattern");
    }

    /**
     * Assert parameter was created with correct value and type
     */
    protected function assertParameterCreated(mixed $expectedValue, ?int $expectedType = null): void
    {
        $parameters = $this->queryBuilderHelper->getCreatedParameters();
        $this->assertNotEmpty($parameters, "No parameters were created");
        
        $lastParam = array_key_last($parameters);
        $this->queryBuilderHelper->assertParameterCreated($lastParam, $expectedValue, $expectedType);
    }
}