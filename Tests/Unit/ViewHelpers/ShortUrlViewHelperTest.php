<?php declare(strict_types=1);

namespace CPSIT\Shortnr\Tests\Unit\ViewHelpers;

use CPSIT\Shortnr\ViewHelpers\ShortUrlViewHelper;
use PHPUnit\Framework\TestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

final class ShortUrlViewHelperTest extends TestCase
{
    private ShortUrlViewHelper $viewHelper;

    protected function setUp(): void
    {
        $this->viewHelper = new ShortUrlViewHelper();

        $renderingContext = $this->createMock(RenderingContextInterface::class);
        $this->viewHelper->setRenderingContext($renderingContext);
        $this->viewHelper->initializeArguments();
    }

    /**
     * @test
     * @dataProvider renderArgumentsDataProvider
     */
    public function testRenderReturnsPlaceholderForAllArgumentCombinations(
        int $target,
        string $type,
        ?int $language,
        bool $absolute,
        array $parameters,
        string $expectedResult
    ): void {
        $this->viewHelper->setArguments([
            'target' => $target,
            'type' => $type,
            'language' => $language,
            'absolute' => $absolute,
            'parameters' => $parameters,
        ]);

        $result = $this->viewHelper->render();

        self::assertSame($expectedResult, $result);
    }


    public static function renderArgumentsDataProvider(): \Generator
    {
        yield 'basic page target' => [
            'target' => 123,
            'type' => 'pages',
            'language' => null,
            'absolute' => false,
            'parameters' => [],
            'expectedResult' => 'TODO: RETURN SHORT URL',
        ];

        yield 'press article with language' => [
            'target' => 456,
            'type' => 'press',
            'language' => 1,
            'absolute' => false,
            'parameters' => [],
            'expectedResult' => 'TODO: RETURN SHORT URL',
        ];

        yield 'absolute URL with parameters' => [
            'target' => 789,
            'type' => 'pages',
            'language' => 0,
            'absolute' => true,
            'parameters' => ['foo' => 'bar', 'baz' => 123],
            'expectedResult' => 'TODO: RETURN SHORT URL',
        ];

        yield 'plugin with complex parameters' => [
            'target' => 999,
            'type' => 'press',
            'language' => 2,
            'absolute' => true,
            'parameters' => [
                'tx_extension_plugin' => [
                    'action' => 'show',
                    'controller' => 'Article',
                    'article' => 42,
                ],
            ],
            'expectedResult' => 'TODO: RETURN SHORT URL',
        ];

        yield 'zero language ID' => [
            'target' => 111,
            'type' => 'pages',
            'language' => 0,
            'absolute' => false,
            'parameters' => [],
            'expectedResult' => 'TODO: RETURN SHORT URL',
        ];

        yield 'custom route type' => [
            'target' => 222,
            'type' => 'custom',
            'language' => null,
            'absolute' => false,
            'parameters' => [],
            'expectedResult' => 'TODO: RETURN SHORT URL',
        ];

        yield 'minimal required arguments only' => [
            'target' => 333,
            'type' => 'pages',
            'language' => null,
            'absolute' => false,
            'parameters' => [],
            'expectedResult' => 'TODO: RETURN SHORT URL',
        ];
    }
}
