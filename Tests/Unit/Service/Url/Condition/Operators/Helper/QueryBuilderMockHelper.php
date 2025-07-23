<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators\Helper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Helper class for creating QueryBuilder mocks with proper expression builders
 * Standardizes QueryBuilder mocking across all operator tests
 */
final class QueryBuilderMockHelper
{
    private TestCase $testCase;
    private array $namedParameterCounter = [];
    private array $createdParameters = [];

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
    }

    /**
     * Creates a fully configured QueryBuilder mock with expression builder
     * 
     * @return QueryBuilder&MockObject
     */
    public function createQueryBuilderMock(): QueryBuilder&MockObject
    {
        $queryBuilder = $this->testCase->createMock(QueryBuilder::class);
        $expressionBuilder = $this->createExpressionBuilderMock();
        
        $queryBuilder->method('expr')
            ->willReturn($expressionBuilder);
            
        $queryBuilder->method('createNamedParameter')
            ->willReturnCallback([$this, 'createNamedParameterCallback']);
            
        $queryBuilder->method('escapeLikeWildcards')
            ->willReturnCallback(fn($value) => addcslashes($value, '%_'));

        return $queryBuilder;
    }

    /**
     * Creates expression builder mock with all SQL expression methods
     * 
     * @return ExpressionBuilder&MockObject
     */
    private function createExpressionBuilderMock(): ExpressionBuilder&MockObject
    {
        $expressionBuilder = $this->testCase->createMock(ExpressionBuilder::class);
        
        // Equality operators
        $expressionBuilder->method('eq')
            ->willReturnCallback(fn($field, $value) => "{$field} = {$value}");
        $expressionBuilder->method('neq')
            ->willReturnCallback(fn($field, $value) => "{$field} != {$value}");
            
        // Comparison operators
        $expressionBuilder->method('lt')
            ->willReturnCallback(fn($field, $value) => "{$field} < {$value}");
        $expressionBuilder->method('lte')
            ->willReturnCallback(fn($field, $value) => "{$field} <= {$value}");
        $expressionBuilder->method('gt')
            ->willReturnCallback(fn($field, $value) => "{$field} > {$value}");
        $expressionBuilder->method('gte')
            ->willReturnCallback(fn($field, $value) => "{$field} >= {$value}");
            
        // Array operators
        $expressionBuilder->method('in')
            ->willReturnCallback(fn($field, $value) => "{$field} IN ({$value})");
        $expressionBuilder->method('notIn')
            ->willReturnCallback(fn($field, $value) => "{$field} NOT IN ({$value})");
            
        // String operators
        $expressionBuilder->method('like')
            ->willReturnCallback(fn($field, $value) => "{$field} LIKE {$value}");
        $expressionBuilder->method('notLike')
            ->willReturnCallback(fn($field, $value) => "{$field} NOT LIKE {$value}");
            
        // Null operators
        $expressionBuilder->method('isNull')
            ->willReturnCallback(fn($field) => "{$field} IS NULL");
        $expressionBuilder->method('isNotNull')
            ->willReturnCallback(fn($field) => "{$field} IS NOT NULL");
            
        // Composite expressions (AND/OR)
        $expressionBuilder->method('and')
            ->willReturnCallback([$this, 'createAndExpression']);
        $expressionBuilder->method('or')
            ->willReturnCallback([$this, 'createOrExpression']);

        return $expressionBuilder;
    }

    /**
     * Callback for createNamedParameter to generate realistic parameter names
     */
    public function createNamedParameterCallback(mixed $value, int $type = null): string
    {
        $paramName = ':dcValue' . count($this->createdParameters);
        $this->createdParameters[$paramName] = ['value' => $value, 'type' => $type];
        return $paramName;
    }

    /**
     * Creates AND composite expression
     */
    public function createAndExpression(...$expressions): CompositeExpression
    {
        $composite = $this->testCase->createMock(CompositeExpression::class);
        $composite->method('__toString')
            ->willReturn('(' . implode(' AND ', array_filter($expressions)) . ')');
        return $composite;
    }

    /**
     * Creates OR composite expression
     */
    public function createOrExpression(...$expressions): CompositeExpression
    {
        $composite = $this->testCase->createMock(CompositeExpression::class);
        $composite->method('__toString')
            ->willReturn('(' . implode(' OR ', array_filter($expressions)) . ')');
        return $composite;
    }

    /**
     * Gets all created parameters for test assertions
     */
    public function getCreatedParameters(): array
    {
        return $this->createdParameters;
    }

    /**
     * Resets parameter tracking between tests
     */
    public function reset(): void
    {
        $this->namedParameterCounter = [];
        $this->createdParameters = [];
    }

    /**
     * Creates a parameter assertion helper for verifying correct parameter creation
     */
    public function assertParameterCreated(string $paramName, mixed $expectedValue, ?int $expectedType = null): void
    {
        $this->testCase->assertArrayHasKey($paramName, $this->createdParameters, 
            "Parameter {$paramName} was not created");
        
        $param = $this->createdParameters[$paramName];
        $this->testCase->assertEquals($expectedValue, $param['value'], 
            "Parameter {$paramName} value mismatch");
            
        if ($expectedType !== null) {
            $this->testCase->assertEquals($expectedType, $param['type'], 
                "Parameter {$paramName} type mismatch");
        }
    }
}