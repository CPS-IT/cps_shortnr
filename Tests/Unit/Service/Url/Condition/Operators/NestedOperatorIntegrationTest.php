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
 * Integration test suite for complex nested operator combinations
 * Tests real-world scenarios from config.yaml showing operator interaction patterns
 * 
 * Based on config patterns like:
 * is_event:
 *   contains: "test2" 
 *   not:
 *     eq: 1
 *     contains: "test"
 */
final class NestedOperatorIntegrationTest extends BaseOperatorTest
{
    private array $operators;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize all operators for integration testing
        $this->operators = [
            'eq' => new EqualOperator(),
            'contains' => new StringContainsOperator(),
            'starts' => new StringStartsOperator(),
            'ends' => new StringEndsOperator(),
            'lt' => new LessOperator(),
            'lte' => new LessOperator(),
            'gt' => new GreaterOperator(),
            'gte' => new GreaterOperator(),
            'between' => new BetweenOperator(),
            'isset' => new IssetOperator(),
            'match' => new RegexMatchOperator(),
            'array_in' => new ArrayInOperator(),
            'not' => new NotOperator(),
        ];
    }

    /**
     * Test simple operator behavior without nesting
     * 
     * @dataProvider simpleOperatorProvider
     */
    public function testSimpleOperatorBehavior(
        string $operatorKey,
        mixed $config,
        string $expectedSqlPattern,
        string $scenario
    ): void {
        $fieldName = 'test_field';
        $operator = $this->operators[$operatorKey];
        
        // Mock database platform for regex operator
        if ($operatorKey === 'match') {
            $this->mockDatabasePlatform('mysql');
        }
        
        $this->assertTrue($operator->supports($config), "Operator should support config for {$scenario}");
        
        $result = $operator->process($fieldName, $config, $this->queryBuilder, null);
        
        $this->assertMatchesRegularExpression($expectedSqlPattern, $result, 
            "SQL pattern mismatch for scenario: {$scenario}");
    }

    public static function simpleOperatorProvider(): array
    {
        return [
            // Basic operators from config examples
            'uid equality' => [
                'eq', 
                123, 
                '/test_field = :dcValue\\d+/', 
                'simple equality check'
            ],
            'contains test2' => [
                'contains', 
                ['contains' => 'test2'], 
                '/test_field LIKE :dcValue\\d+/', 
                'string contains check'
            ],
            'starts with v' => [
                'starts', 
                ['starts' => 'v'], 
                '/test_field LIKE :dcValue\\d+/', 
                'string starts with check'
            ],
            'ends with road' => [
                'ends', 
                ['ends' => 'road'], 
                '/test_field LIKE :dcValue\\d+/', 
                'string ends with check'
            ],
            'less than 30' => [
                'lt', 
                ['lt' => 30], 
                '/test_field < :dcValue\\d+/', 
                'less than comparison'
            ],
            'greater equal 50' => [
                'gte', 
                ['gte' => 50], 
                '/test_field >= :dcValue\\d+/', 
                'greater than or equal comparison'
            ],
            'array in status' => [
                'array_in', 
                ['active', 'pending'], 
                '/test_field IN \\(:dcValue\\d+\\)/', 
                'array membership check'
            ],
            'isset true' => [
                'isset', 
                ['isset' => true], 
                '/test_field IS NOT NULL/', 
                'field existence check'
            ],
            'regex match email' => [
                'match', 
                ['match' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$'], 
                '/test_field REGEXP :dcValue\\d+/', 
                'regex pattern matching'
            ],
        ];
    }

    /**
     * Test NOT operator wrapping behavior based on config examples
     * 
     * @dataProvider notOperatorProvider
     */
    public function testNotOperatorWrapping(
        array $notConfig,
        string $expectedSqlPattern,
        string $scenario
    ): void {
        $fieldName = 'test_field';
        $notOperator = $this->operators['not'];
        
        // Mock database platform for regex operator if needed
        if (isset($notConfig['not']['match'])) {
            $this->mockDatabasePlatform('mysql');
        }
        
        $this->assertTrue($notOperator->supports($notConfig), "NOT operator should support config for {$scenario}");
        
        // NOT operator uses wrap method, simulating ConditionService behavior
        $result = $notOperator->wrap(
            $fieldName, 
            $notConfig, 
            $this->queryBuilder, 
            null,
            function($fieldName, $innerConfig, $queryBuilder, $parent) {
                return $this->processInnerOperator($fieldName, $innerConfig, $queryBuilder, $parent);
            }
        );
        
        // Result should be array from wrap method
        $this->assertIsArray($result, "NOT wrap should return array for {$scenario}");
        $this->assertNotEmpty($result, "NOT wrap result should not be empty for {$scenario}");
        
        // Convert result to string for pattern matching
        $resultString = is_array($result) ? implode(' ', $result) : $result;
        $this->assertMatchesRegularExpression($expectedSqlPattern, $resultString, 
            "NOT pattern mismatch for scenario: {$scenario}");
    }

    public static function notOperatorProvider(): array
    {
        return [
            // Based on config: not: { eq: 1 }
            'not equals 1' => [
                ['not' => ['eq' => 1]], 
                '/test_field != :dcValue\\d+/', 
                'negated equality'
            ],
            // Based on config: not: { contains: "test" }
            'not contains test' => [
                ['not' => ['contains' => 'test']], 
                '/test_field NOT LIKE :dcValue\\d+/', 
                'negated string contains'
            ],
            // Based on config: not: { ends: '-rc' }
            'not ends with rc' => [
                ['not' => ['ends' => '-rc']], 
                '/test_field NOT LIKE :dcValue\\d+/', 
                'negated string ends with'
            ],
            // Based on config: not: { between: [18, 65] }
            'not between ages' => [
                ['not' => ['between' => [18, 65]]], 
                '/\\(test_field < :dcValue\\d+ OR test_field > :dcValue\\d+\\)/', 
                'negated between range'
            ],
            // Based on config: not: ["spam", "bot"]
            'not in blocked users' => [
                ['not' => ['spam', 'bot']], 
                '/test_field NOT IN \\(:dcValue\\d+\\)/', 
                'negated array membership'
            ],
        ];
    }

    /**
     * Test complex nested scenarios from actual config.yaml
     * 
     * @dataProvider complexNestedProvider
     */
    public function testComplexNestedScenarios(
        array $complexConfig,
        array $expectedPatterns,
        string $scenario
    ): void {
        $fieldName = 'test_field';
        
        // Mock database platform for any regex operations
        $this->mockDatabasePlatform('mysql');
        
        // Process each part of the complex configuration
        $results = [];
        foreach ($complexConfig as $operatorKey => $operatorConfig) {
            if ($operatorKey === 'not') {
                // Handle NOT operator wrapping
                $notOperator = $this->operators['not'];
                $result = $notOperator->wrap(
                    $fieldName,
                    ['not' => $operatorConfig],
                    $this->queryBuilder,
                    null,
                    function($fieldName, $innerConfig, $queryBuilder, $parent) {
                        return $this->processInnerOperator($fieldName, $innerConfig, $queryBuilder, $parent);
                    }
                );
                $results[] = is_array($result) ? implode(' ', $result) : $result;
            } else {
                // Handle direct operators
                $operator = $this->findOperatorForConfig([$operatorKey => $operatorConfig]);
                if ($operator) {
                    $result = $operator->process($fieldName, [$operatorKey => $operatorConfig], $this->queryBuilder, null);
                    $results[] = $result;
                }
            }
        }
        
        $this->assertNotEmpty($results, "Should generate results for complex scenario: {$scenario}");
        
        // Verify each expected pattern is found in results
        foreach ($expectedPatterns as $pattern) {
            $patternFound = false;
            foreach ($results as $result) {
                if (preg_match($pattern, $result)) {
                    $patternFound = true;
                    break;
                }
            }
            $this->assertTrue($patternFound, "Pattern {$pattern} not found in results for scenario: {$scenario}");
        }
    }

    public static function complexNestedProvider(): array
    {
        return [
            // Based on config example:
            // is_event:
            //   contains: "test2"
            //   not:
            //     eq: 1
            //     contains: "test"
            'complex is_event logic' => [
                [
                    'contains' => 'test2',
                    'not' => [
                        'eq' => 1,
                        'contains' => 'test'
                    ]
                ],
                [
                    '/test_field LIKE :dcValue\\d+/',  // contains test2
                    '/test_field != :dcValue\\d+/',    // not eq 1  
                    '/test_field NOT LIKE :dcValue\\d+/' // not contains test
                ],
                'complex event validation logic'
            ],
            
            // Based on config example:
            // version:
            //   not:
            //     ends: '-rc'
            //   starts: 'v'
            'version validation logic' => [
                [
                    'not' => ['ends' => '-rc'],
                    'starts' => 'v'
                ],
                [
                    '/test_field NOT LIKE :dcValue\\d+/', // not ends -rc
                    '/test_field LIKE :dcValue\\d+/'      // starts v
                ],
                'version string validation'
            ],
            
            // Based on multiple field validation
            'multi-constraint validation' => [
                [
                    'gte' => 50,      // score >= 50
                    'lt' => 30,       // ranking < 30
                    'isset' => true   // surname exists
                ],
                [
                    '/test_field >= :dcValue\\d+/',    // gte 50
                    '/test_field < :dcValue\\d+/',     // lt 30
                    '/test_field IS NOT NULL/'        // isset true
                ],
                'multiple field constraints'
            ],
        ];
    }

    /**
     * Test operator precedence and interaction patterns
     * 
     * @dataProvider operatorPrecedenceProvider
     */
    public function testOperatorPrecedence(
        array $config,
        string $primaryPattern,
        string $secondaryPattern,
        string $scenario
    ): void {
        $fieldName = 'precedence_field';
        
        // Mock database platform if needed
        if (isset($config['match'])) {
            $this->mockDatabasePlatform('mysql');
        }
        
        $results = [];
        foreach ($config as $operatorKey => $operatorConfig) {
            $operator = $this->findOperatorForConfig([$operatorKey => $operatorConfig]);
            if ($operator) {
                $result = $operator->process($fieldName, [$operatorKey => $operatorConfig], $this->queryBuilder, null);
                $results[] = $result;
            }
        }
        
        $this->assertNotEmpty($results, "Should generate results for precedence test: {$scenario}");
        
        $allResults = implode(' ', $results);
        $this->assertMatchesRegularExpression($primaryPattern, $allResults, 
            "Primary pattern not found for scenario: {$scenario}");
        $this->assertMatchesRegularExpression($secondaryPattern, $allResults, 
            "Secondary pattern not found for scenario: {$scenario}");
    }

    public static function operatorPrecedenceProvider(): array
    {
        return [
            // When both lt and lte are present
            'lt vs lte precedence' => [
                ['lt' => 30, 'lte' => 40],
                '/precedence_field </',  // Both should be present
                '/precedence_field <=/',
                'less than operators precedence'
            ],
            
            // Complex string operations
            'string operations combination' => [
                ['contains' => 'test', 'starts' => 'prefix'],
                '/precedence_field LIKE :dcValue\\d+/',  // contains pattern
                '/precedence_field LIKE :dcValue\\d+/', // starts pattern
                'string operation combinations'
            ],
            
            // Range and equality combinations
            'range and equality mix' => [
                ['eq' => 42, 'gte' => 50],
                '/precedence_field = :dcValue\\d+/',   // equality
                '/precedence_field >= :dcValue\\d+/',  // greater equal
                'range and equality mixing'
            ],
        ];
    }

    /**
     * Helper method to process inner operators for NOT wrapping
     */
    private function processInnerOperator(string $fieldName, mixed $config, $queryBuilder, $parent): array
    {
        $operator = $this->findOperatorForConfig($config);
        if (!$operator) {
            return [];
        }
        
        $result = $operator->process($fieldName, $config, $queryBuilder, $parent);
        
        // Ensure result is always an array for NotOperator compatibility
        return is_array($result) ? $result : [$result];
    }

    /**
     * Helper method to find appropriate operator for config
     */
    private function findOperatorForConfig(mixed $config): ?object
    {
        if (is_array($config)) {
            foreach ($config as $key => $value) {
                if (isset($this->operators[$key])) {
                    return $this->operators[$key];
                }
            }
            
            // Check for array list (ArrayInOperator)
            if (array_is_list($config)) {
                return $this->operators['array_in'];
            }
        }
        
        // Default to equality for scalars
        return $this->operators['eq'];
    }

    /**
     * Helper method to mock database platform for regex tests
     */
    private function mockDatabasePlatform(string $platformName): void
    {
        $connection = $this->createMock(\TYPO3\CMS\Core\Database\Connection::class);
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getName')->willReturn($platformName);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $this->queryBuilder->method('getConnection')->willReturn($connection);
    }

    /**
     * Test real config.yaml scenario patterns
     * 
     * @dataProvider realConfigScenarioProvider
     */
    public function testRealConfigScenarios(
        string $fieldName,
        mixed $fieldConfig, 
        array $expectedPatterns,
        string $scenario
    ): void {
        // Mock database platform for regex operations
        $this->mockDatabasePlatform('mysql');
        
        $results = [];
        
        // Handle different config types
        if (is_array($fieldConfig)) {
            // Process each configuration key
            foreach ($fieldConfig as $operatorKey => $operatorValue) {
                if ($operatorKey === 'not') {
                    // Handle NOT operator
                    $notOperator = $this->operators['not'];
                    $result = $notOperator->wrap(
                        $fieldName,
                        ['not' => $operatorValue],
                        $this->queryBuilder,
                        null,
                        function($fieldName, $innerConfig, $queryBuilder, $parent) {
                            return $this->processInnerOperator($fieldName, $innerConfig, $queryBuilder, $parent);
                        }
                    );
                    $results[] = is_array($result) ? implode(' ', $result) : $result;
                } else {
                    // Handle direct operators
                    $operator = $this->findOperatorForConfig([$operatorKey => $operatorValue]);
                    if ($operator) {
                        $result = $operator->process($fieldName, [$operatorKey => $operatorValue], $this->queryBuilder, null);
                        $results[] = $result;
                    }
                }
            }
        } else {
            // Handle scalar values (direct equality)
            $operator = $this->findOperatorForConfig($fieldConfig);
            if ($operator) {
                $result = $operator->process($fieldName, $fieldConfig, $this->queryBuilder, null);
                $results[] = $result;
            }
        }
        
        $this->assertNotEmpty($results, "Should generate results for real config scenario: {$scenario}");
        
        // Verify all expected patterns are present
        $allResults = implode(' ', $results);
        foreach ($expectedPatterns as $pattern) {
            $this->assertMatchesRegularExpression($pattern, $allResults, 
                "Pattern {$pattern} not found in real config scenario: {$scenario}");
        }
    }

    public static function realConfigScenarioProvider(): array
    {
        return [
            // From config.yaml: uid: "{match-2}", sysLanguageUid: "{match-3}"
            'basic field mapping' => [
                'uid',
                '{match-2}',  // Scalar value becomes equality
                ['/uid = :dcValue\\d+/'],
                'basic UID field mapping'
            ],
            
            // From config.yaml: status: [ "active", "pending" ]
            'status array check' => [
                'status',
                ['active', 'pending'],  // Array becomes IN operator
                ['/status IN \\(:dcValue\\d+\\)/'],
                'status array membership'
            ],
            
            // From config.yaml: name: { contains: "test" }
            'name contains check' => [
                'name',
                ['contains' => 'test'],
                ['/name LIKE :dcValue\\d+/'],
                'name contains validation'
            ],
            
            // From config.yaml: lastName: { not: { contains: "test" } }
            'lastname not contains' => [
                'lastName',
                ['not' => ['contains' => 'test']],
                ['/lastName NOT LIKE :dcValue\\d+/'],
                'lastname negated contains'
            ],
            
            // From config.yaml: surname: { isset: true }
            'surname existence check' => [
                'surname',
                ['isset' => true],
                ['/surname IS NOT NULL/'],
                'surname field existence'
            ],
            
            // From config.yaml: email: { match: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$" }
            'email regex validation' => [
                'email',
                ['match' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$'],
                ['/email REGEXP :dcValue\\d+/'],
                'email regex pattern matching'
            ],
            
            // From config.yaml: age: { not: { between: [ 18, 65 ] } }
            'age not between range' => [
                'age',
                ['not' => ['between' => [18, 65]]],
                ['/\\(age < :dcValue\\d+ OR age > :dcValue\\d+\\)/'],
                'age outside range validation'
            ],
        ];
    }
}