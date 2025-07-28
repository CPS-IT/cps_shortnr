<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Url\Condition\Operators;

use CPSIT\ShortNr\Service\Url\Condition\Operators\StringEndsOperator;

/**
 * Comprehensive test suite for StringEndsOperator  
 * Tests string suffix matching with LIKE operator
 * 
 * Based on config syntax: street: { ends: 'road' }, version: { not: { ends: '-rc' } }
 */
final class StringEndsOperatorTest extends BaseOperatorTest
{
    private StringEndsOperator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = new StringEndsOperator();
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
            // Arrays with 'ends' key (should be supported)
            'ends with simple string' => [['ends' => 'road'], true, 'simple suffix'],
            'ends with complex string' => [['ends' => '_suffix'], true, 'complex suffix'],
            'ends with empty string' => [['ends' => ''], true, 'empty suffix'],
            'ends with special chars' => [['ends' => '.com'], true, 'special characters'],
            'ends with number string' => [['ends' => '123'], true, 'numeric suffix'],
            'ends with whitespace' => [['ends' => ' end'], true, 'whitespace suffix'],
            'ends with unicode' => [['ends' => 'café'], true, 'unicode characters'],
            'ends with extension' => [['ends' => '.txt'], true, 'file extension'],
            'complex array with ends' => [['ends' => 'suffix', 'other' => 'ignored'], true, 'complex array with ends'],
            
            // Arrays without 'ends' key (should not be supported)
            'empty array' => [[], false, 'empty array'],
            'array without ends' => [['contains' => 'test'], false, 'array with other operators'],
            'list array' => [['active', 'pending'], false, 'sequential array'],
            'complex array without ends' => [['not' => ['eq' => 'test']], false, 'nested without ends'],
            
            // Scalar values (should not be supported - ends requires array syntax)
            'scalar string' => ['test', false, 'scalar string'],
            'scalar integer' => [42, false, 'scalar integer'],
            'scalar null' => [null, false, 'scalar null'],
            'scalar boolean' => [true, false, 'scalar boolean'],
        ];
    }

    /**
     * @dataProvider processDataProvider
     */
    public function testProcess(
        mixed $fieldConfig,
        bool $hasNotInHistory,
        string $expectedOperator,
        string $expectedPattern,
        string $scenario
    ): void {
        $fieldName = 'test_field';
        $history = $this->createOperatorHistory($hasNotInHistory);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify SQL expression format
        $expectedRegex = "/^{$fieldName} {$expectedOperator} :dcValue\\d+$/";
        $this->assertSqlExpression($expectedRegex, $result);
        
        // Verify parameter creation with proper pattern
        $this->assertParameterCreated($expectedPattern);
    }

    public static function processDataProvider(): array
    {
        $scenarios = [];
        
        // Test various string suffixes with and without NOT operator
        $stringSuffixes = [
            'simple suffix' => 'road',
            'file extension' => '.txt',
            'version suffix' => '-rc',
            'number suffix' => '123',
            'special chars' => '.com',
            'with spaces' => ' end',
            'empty string' => '',
            'single char' => 'e',
            'unicode chars' => 'café',
            'with wildcards' => 'test%_',
            'mixed case' => 'Road',
            'long suffix' => '_very_long_suffix_string',
            'domain suffix' => '.example.com',
            'path suffix' => '/index.html',
        ];
        
        foreach ($stringSuffixes as $suffixKey => $suffix) {
            $escapedSuffix = addcslashes($suffix, '%_'); // Simulates escapeLikeWildcards
            
            // Without NOT operator - uses LIKE with % prefix
            $scenarios["{$suffixKey} without NOT"] = [
                'fieldConfig' => ['ends' => $suffix],
                'hasNotInHistory' => false,
                'expectedOperator' => 'LIKE',
                'expectedPattern' => '%' . $escapedSuffix,
                'scenario' => "{$suffixKey} normal ends with",
            ];
            
            // With NOT operator - uses NOT LIKE with % prefix
            $scenarios["{$suffixKey} with NOT"] = [
                'fieldConfig' => ['ends' => $suffix],
                'hasNotInHistory' => true,
                'expectedOperator' => 'NOT LIKE',
                'expectedPattern' => '%' . $escapedSuffix,
                'scenario' => "{$suffixKey} negated ends with",
            ];
        }
        
        return $scenarios;
    }

    /**
     * @dataProvider fieldNameVariationsProvider
     */
    public function testProcessWithVariousFieldNames(string $fieldName): void
    {
        $fieldConfig = ['ends' => 'test'];
        $history = $this->createOperatorHistory(false);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify field name is preserved in expression
        $this->assertStringContainsString($fieldName, $result, 
            "Field name '{$fieldName}' not found in expression");
        $this->assertStringContainsString('LIKE', $result, 
            "LIKE operator not found in expression");
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
        
        // Verify operator type based on NOT history
        $expectedOp = $hasNotInHistory ? 'NOT LIKE' : 'LIKE';
        $this->assertStringContainsString($expectedOp, $result, 
            "Expected operator '{$expectedOp}' not found for scenario: {$scenario}");
    }

    public static function edgeCaseDataProvider(): array
    {
        return [
            // Special string patterns
            'empty suffix without NOT' => [['ends' => ''], false, 'matches all strings', 'empty suffix'],
            'empty suffix with NOT' => [['ends' => ''], true, 'matches no strings', 'empty suffix negated'],
            'single char suffix' => [['ends' => 'e'], false, 'matches %e', 'minimal suffix'],
            'whitespace suffix' => [['ends' => ' '], false, 'matches space suffix', 'space character'],
            
            // File extensions
            'txt extension' => [['ends' => '.txt'], false, 'matches file extension', 'text file extension'],
            'complex extension' => [['ends' => '.tar.gz'], false, 'matches complex extension', 'compressed archive'],
            'no dot extension' => [['ends' => 'html'], false, 'matches extension without dot', 'html without dot'],
            
            // Version suffixes
            'rc suffix' => [['ends' => '-rc'], false, 'matches release candidate', 'release candidate suffix'],
            'beta suffix' => [['ends' => '-beta'], false, 'matches beta version', 'beta version suffix'],
            'version number' => [['ends' => '.1'], false, 'matches version number', 'version number suffix'],
            
            // Special characters that need escaping
            'wildcard percent' => [['ends' => 'test%'], false, 'escapes percent', 'contains SQL wildcard %'],
            'wildcard underscore' => [['ends' => 'test_'], false, 'escapes underscore', 'contains SQL wildcard _'],
            'both wildcards' => [['ends' => '%_test'], false, 'escapes both wildcards', 'contains both SQL wildcards'],
            'wildcards at end' => [['ends' => 'test%_'], false, 'escapes at end', 'wildcards at ending'],
            
            // Domain and URL suffixes
            'domain suffix' => [['ends' => '.com'], false, 'matches domain', 'domain extension'],
            'subdomain suffix' => [['ends' => '.example.com'], false, 'matches subdomain', 'full domain suffix'],
            'path suffix' => [['ends' => '/index.html'], false, 'matches path', 'URL path suffix'],
            'query suffix' => [['ends' => '?param=value'], false, 'matches query', 'URL query suffix'],
            
            // Unicode and international characters
            'unicode suffix' => [['ends' => 'café'], false, 'matches unicode', 'unicode characters'],
            'emoji suffix' => [['ends' => '🚀'], false, 'matches emoji', 'emoji character'],
            'mixed unicode' => [['ends' => 'café_test'], false, 'matches mixed unicode', 'mixed characters'],
            
            // Complex scenarios
            'long suffix' => [
                ['ends' => '_very_long_suffix_string_that_should_work'], 
                false, 
                'matches long suffix', 
                'very long suffix string'
            ],
            'special chars suffix' => [
                ['ends' => '@#$%^&*()'], 
                false, 
                'matches special chars', 
                'special characters suffix'
            ],
            
            // Multi-key array
            'multi-key array' => [
                ['ends' => 'suffix', 'ignored' => 'value'], 
                false, 
                'uses ends value only', 
                'multi-key array'
            ],
        ];
    }

    /**
     * Test wildcard escaping functionality
     * 
     * @dataProvider wildcardEscapingProvider
     */
    public function testWildcardEscaping(
        string $inputSuffix,
        string $expectedEscapedPattern,
        string $scenario
    ): void {
        $fieldName = 'wildcard_field';
        $fieldConfig = ['ends' => $inputSuffix];
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify parameter was created with properly escaped pattern
        $this->assertParameterCreated($expectedEscapedPattern);
    }

    public static function wildcardEscapingProvider(): array
    {
        return [
            'no wildcards' => ['test', '%test', 'normal string'],
            'with percent' => ['test%', '%test\\%', 'contains percent wildcard'],
            'with underscore' => ['test_', '%test\\_', 'contains underscore wildcard'],
            'both wildcards' => ['%_test', '%\\%\\_test', 'contains both wildcards'],
            'wildcards at end' => ['test%_', '%test\\%\\_', 'wildcards at end'],
            'wildcards in middle' => ['te%st_', '%te\\%st\\_', 'wildcards in middle'],
            'multiple percents' => ['%test%', '%\\%test\\%', 'multiple percent signs'],
            'multiple underscores' => ['_test_', '%\\_test\\_', 'multiple underscores'],
            'complex pattern' => ['%test_value%', '%\\%test\\_value\\%', 'complex wildcard pattern'],
        ];
    }

    /**
     * Test behavior with null history
     */
    public function testProcessWithNullHistory(): void
    {
        $fieldName = 'null_history_field';
        $fieldConfig = ['ends' => 'test'];
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, null);
        
        // Should behave as normal LIKE when no history
        $this->assertStringContainsString('LIKE', $result);
        $this->assertStringNotContainsString('NOT LIKE', $result);
    }

    /**
     * Test that operator correctly extracts suffix from array config
     */
    public function testArrayConfigValueExtraction(): void
    {
        $fieldName = 'extraction_field';
        $testSuffix = 'extracted_suffix';
        $fieldConfig = ['ends' => $testSuffix, 'other_key' => 'should_be_ignored'];
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify that only the 'ends' value was used for parameter (with % prefix and escaping)
        $expectedEscapedValue = '%' . addcslashes($testSuffix, '%_');
        $this->assertParameterCreated($expectedEscapedValue);
    }

    /**
     * Test pattern generation consistency
     * 
     * @dataProvider patternGenerationProvider
     */
    public function testPatternGeneration(
        string $suffix,
        bool $hasNot,
        string $expectedSqlOp,
        string $expectedPattern,
        string $scenario
    ): void {
        $fieldName = 'pattern_field';
        $fieldConfig = ['ends' => $suffix];
        $history = $this->createOperatorHistory($hasNot);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertStringContainsString($expectedSqlOp, $result, 
            "SQL operator incorrect for scenario: {$scenario}");
        $this->assertParameterCreated($expectedPattern);
    }

    public static function patternGenerationProvider(): array
    {
        return [
            // Basic patterns
            'simple normal' => ['test', false, 'LIKE', '%test', 'simple suffix normal'],
            'simple negated' => ['test', true, 'NOT LIKE', '%test', 'simple suffix negated'],
            
            // Empty patterns
            'empty normal' => ['', false, 'LIKE', '%', 'empty suffix normal'],
            'empty negated' => ['', true, 'NOT LIKE', '%', 'empty suffix negated'],
            
            // File extension patterns
            'txt normal' => ['.txt', false, 'LIKE', '%.txt', 'txt extension normal'],
            'txt negated' => ['.txt', true, 'NOT LIKE', '%.txt', 'txt extension negated'],
            
            // Special character patterns
            'wildcard normal' => ['%test', false, 'LIKE', '%\\%test', 'wildcard suffix normal'],
            'wildcard negated' => ['%test', true, 'NOT LIKE', '%\\%test', 'wildcard suffix negated'],
            'underscore normal' => ['_test', false, 'LIKE', '%\\_test', 'underscore suffix normal'],
            'underscore negated' => ['_test', true, 'NOT LIKE', '%\\_test', 'underscore suffix negated'],
            
            // Complex patterns
            'complex normal' => ['%suf_fix', false, 'LIKE', '%\\%suf\\_fix', 'complex suffix normal'],
            'complex negated' => ['%suf_fix', true, 'NOT LIKE', '%\\%suf\\_fix', 'complex suffix negated'],
            
            // Version patterns
            'rc normal' => ['-rc', false, 'LIKE', '%-rc', 'release candidate normal'],
            'rc negated' => ['-rc', true, 'NOT LIKE', '%-rc', 'release candidate negated'],
        ];
    }

    /**
     * Test integration with QueryBuilder escape functionality
     */
    public function testQueryBuilderEscapeIntegration(): void
    {
        $fieldName = 'escape_field';
        $suffixWithWildcards = 'test%_value';
        $fieldConfig = ['ends' => $suffixWithWildcards];
        $history = $this->createOperatorHistory(false);
        
        $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        // Verify that escapeLikeWildcards was called and result includes escaped pattern
        $expectedEscapedPattern = '%test\\%\\_value'; // QueryBuilderMockHelper simulates escapeLikeWildcards
        $this->assertParameterCreated($expectedEscapedPattern);
    }

    /**
     * Test comprehensive string suffix scenarios from config
     * 
     * @dataProvider configScenarioProvider
     */
    public function testConfigScenarios(
        array $fieldConfig,
        bool $hasNot,
        string $expectedOperator,
        string $expectedPattern,
        string $scenario
    ): void {
        $fieldName = 'config_field';
        $history = $this->createOperatorHistory($hasNot);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertStringContainsString($expectedOperator, $result, 
            "Config scenario failed for {$scenario}");
        $this->assertParameterCreated($expectedPattern);
    }

    public static function configScenarioProvider(): array
    {
        return [
            // Based on actual config examples
            'street ends road' => [
                ['ends' => 'road'],
                false,
                'LIKE',
                '%road',
                'street field ends with road'
            ],
            'version not ends rc' => [
                ['ends' => '-rc'],
                true,
                'NOT LIKE',
                '%-rc',
                'version field not ends with -rc'
            ],
            'file extension' => [
                ['ends' => '.txt'],
                false,
                'LIKE',
                '%.txt',
                'file extension pattern'
            ],
            'domain suffix' => [
                ['ends' => '.com'],
                false,
                'LIKE',
                '%.com',
                'domain extension pattern'
            ],
            'complex business suffix' => [
                ['ends' => '_processed'],
                false,
                'LIKE',
                '%\\_processed', // Underscore gets escaped
                'business logic suffix'
            ],
            'version with wildcard' => [
                ['ends' => '_v%'],
                false,
                'LIKE',
                '%\\_v\\%', // Both underscore and percent escaped
                'version with SQL wildcards'
            ],
        ];
    }

    /**
     * Test file extension matching scenarios
     * 
     * @dataProvider fileExtensionProvider
     */
    public function testFileExtensionScenarios(
        string $extension,
        bool $hasNot,
        string $expectedOperator,
        string $expectedPattern,
        string $scenario
    ): void {
        $fieldName = 'filename';
        $fieldConfig = ['ends' => $extension];
        $history = $this->createOperatorHistory($hasNot);
        
        $result = $this->operator->process($fieldName, $fieldConfig, $this->queryBuilder, $history);
        
        $this->assertStringContainsString($expectedOperator, $result, 
            "File extension scenario failed for {$scenario}");
        $this->assertParameterCreated($expectedPattern);
    }

    public static function fileExtensionProvider(): array
    {
        return [
            'text file' => ['.txt', false, 'LIKE', '%.txt', 'text file extension'],
            'not text file' => ['.txt', true, 'NOT LIKE', '%.txt', 'exclude text files'],
            'image file' => ['.jpg', false, 'LIKE', '%.jpg', 'image file extension'],
            'not image file' => ['.jpg', true, 'NOT LIKE', '%.jpg', 'exclude image files'],
            'archive file' => ['.tar.gz', false, 'LIKE', '%.tar.gz', 'compressed archive'],
            'not archive file' => ['.tar.gz', true, 'NOT LIKE', '%.tar.gz', 'exclude archives'],
            'executable file' => ['.exe', false, 'LIKE', '%.exe', 'executable extension'],
            'backup file' => ['.bak', false, 'LIKE', '%.bak', 'backup file extension'],
        ];
    }
}