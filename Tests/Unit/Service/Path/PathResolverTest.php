<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Path;

use CPSIT\ShortNr\Service\Path\PathResolverInterface;
use CPSIT\ShortNr\Service\Path\Typo3PathResolver;
use PHPUnit\Framework\TestCase;

class PathResolverTest extends TestCase
{
    private PathResolverInterface $pathResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pathResolver = new Typo3PathResolver();
    }

    public static function pathDataProvider(): array
    {
        return [
            'simple relative path' => [
                'inputPath' => 'config/app.yaml',
                'expectsCall' => true
            ],
            'absolute path' => [
                'inputPath' => '/absolute/path/config.yaml',
                'expectsCall' => true
            ],
            'empty path' => [
                'inputPath' => '',
                'expectsCall' => true
            ],
            'EXT: prefix path' => [
                'inputPath' => 'EXT:extension/config.yaml',
                'expectsCall' => true
            ]
        ];
    }

    /**
     * @dataProvider pathDataProvider
     */
    public function testGetAbsolutePathCallsGeneralUtility(string $inputPath, bool $expectsCall): void
    {
        if ($expectsCall) {
            $result = $this->pathResolver->getAbsolutePath($inputPath);
            $this->assertIsString($result);
        }
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(PathResolverInterface::class, $this->pathResolver);
    }
}