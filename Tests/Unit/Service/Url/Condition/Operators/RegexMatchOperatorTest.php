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
     * @dataProvide processDataProvider
     */
    public function testProcess(
        mixed $fieldConfig,
        bool $hasNotInHistory,
        string $expectedPattern,
        string $scenario
    ): void {
        $fieldName = 'test_field';
        $history = $this->createOperatorHistory($hasNotInHistory);

        $result = $this->operator->postResultProcess($fieldName, $fieldConfig, [], null);
    }
}
