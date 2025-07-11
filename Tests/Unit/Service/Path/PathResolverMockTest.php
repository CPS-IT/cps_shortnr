<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Service\Path;

use CPSIT\ShortNr\Service\Path\PathResolverInterface;
use PHPUnit\Framework\TestCase;

class PathResolverMockTest extends TestCase
{
    public static function pathResolutionDataProvider(): array
    {
        return [
            'simple relative path' => [
                'inputPath' => 'config/app.yaml',
                'expectedPath' => '/var/www/html/config/app.yaml'
            ],
            'absolute path unchanged' => [
                'inputPath' => '/absolute/path/config.yaml',
                'expectedPath' => '/absolute/path/config.yaml'
            ],
            'EXT: prefix resolution' => [
                'inputPath' => 'EXT:extension/config.yaml',
                'expectedPath' => '/var/www/html/typo3conf/ext/extension/config.yaml'
            ],
            'empty path' => [
                'inputPath' => '',
                'expectedPath' => ''
            ]
        ];
    }

    /**
     * @dataProvider pathResolutionDataProvider
     */
    public function testPathResolverMockingInTests(string $inputPath, string $expectedPath): void
    {
        $pathResolver = $this->createMock(PathResolverInterface::class);
        
        $pathResolver->method('getAbsolutePath')
            ->with($inputPath)
            ->willReturn($expectedPath);
        
        $result = $pathResolver->getAbsolutePath($inputPath);
        
        $this->assertSame($expectedPath, $result);
    }

    public function testPathResolverCanBeEasilyConfigure(): void
    {
        $pathResolver = $this->createMock(PathResolverInterface::class);
        
        // Configure different return values for different inputs
        $pathResolver->method('getAbsolutePath')
            ->willReturnMap([
                ['config.yaml', '/resolved/config.yaml'],
                ['EXT:ext/file.yaml', '/typo3conf/ext/ext/file.yaml'],
                ['', '']
            ]);
        
        $this->assertSame('/resolved/config.yaml', $pathResolver->getAbsolutePath('config.yaml'));
        $this->assertSame('/typo3conf/ext/ext/file.yaml', $pathResolver->getAbsolutePath('EXT:ext/file.yaml'));
        $this->assertSame('', $pathResolver->getAbsolutePath(''));
    }

    public function testPathResolverInterfaceDefinition(): void
    {
        $pathResolver = $this->createMock(PathResolverInterface::class);
        
        $this->assertInstanceOf(PathResolverInterface::class, $pathResolver);
        $this->assertTrue(method_exists($pathResolver, 'getAbsolutePath'));
        
        $reflection = new \ReflectionMethod(PathResolverInterface::class, 'getAbsolutePath');
        $this->assertSame('string', $reflection->getReturnType()->getName());
    }
}