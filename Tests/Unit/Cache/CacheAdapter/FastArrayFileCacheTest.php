<?php declare(strict_types=1);

namespace CPSIT\Shortnr\Tests\Unit\Cache\CacheAdapter;

use CPSIT\Shortnr\Cache\CacheAdapter\FastArrayFileCache;
use CPSIT\Shortnr\Config\ExtensionSetup;
use CPSIT\Shortnr\Exception\ShortNrCacheException;
use CPSIT\Shortnr\Service\PlatformAdapter\FileSystem\FileSystemInterface;
use PHPUnit\Framework\TestCase;

class FastArrayFileCacheTest extends TestCase
{
    private FastArrayFileCache $cache;
    private FileSystemInterface $fileSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystem = $this->createMock(FileSystemInterface::class);
        $this->cache = new FastArrayFileCache($this->fileSystem);

        $this->clearStaticRuntimeCache();
    }

    protected function tearDown(): void
    {
        $this->clearStaticRuntimeCache();
        parent::tearDown();
    }

    private function clearStaticRuntimeCache(): void
    {
        $reflection = new \ReflectionClass(FastArrayFileCache::class);
        $property = $reflection->getProperty('runtimeCache');
        $property->setValue(null, []);
    }

    public static function cacheDataProvider(): array
    {
        return [
            'simple array data' => [
                'data' => ['key' => 'value', 'other' => 'data'],
                'suffix' => 'test'
            ],
            'nested array data' => [
                'data' => [
                    'database' => ['host' => 'localhost', 'port' => 3306],
                    'cache' => ['enabled' => true, 'ttl' => 3600]
                ],
                'suffix' => 'nested'
            ],
            'empty array' => [
                'data' => [],
                'suffix' => 'empty'
            ],
            'array with null values' => [
                'data' => ['key' => null, 'empty' => '', 'zero' => 0],
                'suffix' => 'nulls'
            ],
            'array with mixed types' => [
                'data' => [
                    'string' => 'value',
                    'integer' => 42,
                    'float' => 3.14,
                    'boolean' => true,
                    'array' => [1, 2, 3]
                ],
                'suffix' => 'mixed'
            ]
        ];
    }

    /**
     * @dataProvider cacheDataProvider
     */
    public function testWriteAndReadArrayFileCache(array $data, string $suffix): void
    {
        $expectedCacheFile = $this->getExpectedCacheFilePath($suffix);
        $expectedTempFile = '/tmp/temp_cache_file';
        $expectedPhpCode = "<?php\n\nreturn " . var_export($data, true) . ";\n";

        $this->fileSystem->method('file_exists')
            ->willReturnCallback(function($path) use ($expectedCacheFile) {
                if ($path === dirname($expectedCacheFile)) {
                    return true; // Cache directory exists
                }
                if ($path === $expectedCacheFile) {
                    return true; // Cache file exists for reading
                }
                return false;
            });

        $this->fileSystem->method('tempnam')
            ->with(dirname($expectedCacheFile), ExtensionSetup::CACHE_KEY)
            ->willReturn($expectedTempFile);

        $this->fileSystem->expects($this->once())
            ->method('file_put_contents')
            ->with($expectedTempFile, $expectedPhpCode, LOCK_EX);

        $this->fileSystem->expects($this->once())
            ->method('rename')
            ->with($expectedTempFile, $expectedCacheFile);

        $this->fileSystem->method('require')
            ->with($expectedCacheFile)
            ->willReturn($data);

        $this->cache->writeArrayFileCache($data, $suffix);

        $result = $this->cache->readArrayFileCache($suffix);

        $this->assertSame($data, $result);
    }

    public static function cacheReadScenariosDataProvider(): array
    {
        return [
            'cache file exists with valid data' => [
                'fileExists' => true,
                'requireResult' => ['cached' => 'data'],
                'expectedResult' => ['cached' => 'data']
            ],
            'cache file exists with invalid data' => [
                'fileExists' => true,
                'requireResult' => 'not an array',
                'expectedResult' => null
            ],
            'cache file exists but require throws exception' => [
                'fileExists' => true,
                'requireResult' => new \Exception('Parse error'),
                'expectedResult' => null
            ],
            'cache file does not exist' => [
                'fileExists' => false,
                'requireResult' => null,
                'expectedResult' => null
            ]
        ];
    }

    /**
     * @dataProvider cacheReadScenariosDataProvider
     */
    public function testReadArrayFileCacheHandlesDifferentScenarios(
        bool $fileExists,
        mixed $requireResult,
        ?array $expectedResult
    ): void {
        $suffix = 'test';
        $expectedCacheFile = $this->getExpectedCacheFilePath($suffix);

        $this->fileSystem->method('file_exists')
            ->with($expectedCacheFile)
            ->willReturn($fileExists);

        if ($fileExists) {
            if ($requireResult instanceof \Exception) {
                $this->fileSystem->method('require')
                    ->with($expectedCacheFile)
                    ->willThrowException($requireResult);
            } else {
                $this->fileSystem->method('require')
                    ->with($expectedCacheFile)
                    ->willReturn($requireResult);
            }
        }

        $result = $this->cache->readArrayFileCache($suffix);

        $this->assertSame($expectedResult, $result);
    }

    public static function cacheWriteErrorDataProvider(): array
    {
        return [
            'cannot create cache directory' => [
                'dirExists' => false,
                'mkdirResult' => false,
                'expectedException' => ShortNrCacheException::class,
                'expectedMessage' => 'Could not create dir:',
                'filePutContentsException' => null,
                'renameException' => null
            ],
            'file_put_contents fails' => [
                'dirExists' => true,
                'mkdirResult' => true,
                'expectedException' => ShortNrCacheException::class,
                'expectedMessage' => 'Could not write Cache: Write failed',
                'filePutContentsException' => new \Exception('Write failed'),
                'renameException' => null
            ],
            'rename fails' => [
                'dirExists' => true,
                'mkdirResult' => true,
                'expectedException' => ShortNrCacheException::class,
                'expectedMessage' => 'Could not write Cache: Rename failed',
                'filePutContentsException' => null,
                'renameException' => new \Exception('Rename failed')
            ]
        ];
    }

    /**
     * @dataProvider cacheWriteErrorDataProvider
     */
    public function testWriteArrayFileCacheHandlesErrors(
        bool $dirExists,
        bool $mkdirResult,
        string $expectedException,
        string $expectedMessage,
        ?\Exception $filePutContentsException,
        ?\Exception $renameException
    ): void {
        $data = ['test' => 'data'];
        $suffix = 'error';
        $expectedCacheFile = $this->getExpectedCacheFilePath($suffix);
        $expectedTempFile = '/tmp/temp_cache_file';

        $this->fileSystem->method('file_exists')
            ->willReturnMap([
                [dirname($expectedCacheFile), $dirExists]
            ]);

        if (!$dirExists) {
            $this->fileSystem->method('mkdir')
                ->with(dirname($expectedCacheFile), 0755, true)
                ->willReturn($mkdirResult);
        }

        if ($dirExists || $mkdirResult) {
            $this->fileSystem->method('tempnam')
                ->with(dirname($expectedCacheFile), ExtensionSetup::CACHE_KEY)
                ->willReturn($expectedTempFile);
        }

        if ($filePutContentsException) {
            $this->fileSystem->method('file_put_contents')
                ->willThrowException($filePutContentsException);

            $this->fileSystem->expects($this->once())
                ->method('unlink')
                ->with($expectedTempFile);
        }

        if ($renameException) {
            $this->fileSystem->method('rename')
                ->willThrowException($renameException);

            $this->fileSystem->expects($this->once())
                ->method('unlink')
                ->with($expectedTempFile);
        }

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);

        $this->cache->writeArrayFileCache($data, $suffix);
    }

    public static function invalidationDataProvider(): array
    {
        return [
            'cache file exists' => [
                'fileExists' => true,
                'expectUnlink' => true
            ],
            'cache file does not exist' => [
                'fileExists' => false,
                'expectUnlink' => false
            ]
        ];
    }

    /**
     * @dataProvider invalidationDataProvider
     */
    public function testInvalidateFileCacheHandlesDifferentScenarios(bool $fileExists, bool $expectUnlink): void
    {
        $suffix = 'test';
        $expectedCacheFile = $this->getExpectedCacheFilePath($suffix);

        $this->fileSystem->method('file_exists')
            ->with($expectedCacheFile)
            ->willReturn($fileExists);

        if ($expectUnlink) {
            $this->fileSystem->expects($this->once())
                ->method('unlink')
                ->with($expectedCacheFile);
        } else {
            $this->fileSystem->expects($this->never())
                ->method('unlink');
        }

        $this->cache->invalidateFileCache($suffix);

        $result = $this->cache->readArrayFileCache($suffix);
        $this->assertNull($result);
    }

    public static function fileModificationTimeDataProvider(): array
    {
        return [
            'file exists with valid timestamp' => [
                'filemtime' => 1234567890,
                'expectedResult' => 1234567890
            ],
            'file does not exist' => [
                'filemtime' => false,
                'expectedResult' => null
            ],
            'filemtime returns 0' => [
                'filemtime' => 0,
                'expectedResult' => 0
            ]
        ];
    }

    /**
     * @dataProvider fileModificationTimeDataProvider
     */
    public function testGetFileModificationTimeHandlesDifferentScenarios(int|false $filemtime, ?int $expectedResult): void
    {
        $suffix = 'test';
        $expectedCacheFile = $this->getExpectedCacheFilePath($suffix);

        $this->fileSystem->method('filemtime')
            ->with($expectedCacheFile)
            ->willReturn($filemtime);

        $result = $this->cache->getFileModificationTime($suffix);

        $this->assertSame($expectedResult, $result);
    }

    public static function suffixDataProvider(): array
    {
        return [
            'empty suffix' => [
                'suffix' => '',
                'expectedFileName' => 'config.php'
            ],
            'simple suffix' => [
                'suffix' => 'test',
                'expectedFileName' => 'configtest.php'
            ],
            'hash suffix' => [
                'suffix' => 'a1b2c3d4e5',
                'expectedFileName' => 'configa1b2c3d4e5.php'
            ]
        ];
    }

    /**
     * @dataProvider suffixDataProvider
     */
    public function testCacheFilePathGenerationWithDifferentSuffixes(string $suffix, string $expectedFileName): void
    {
        $data = ['test' => 'data'];

        $this->fileSystem->method('file_exists')
            ->willReturn(true);

        $this->fileSystem->method('tempnam')
            ->willReturn('/tmp/temp_file');

        $this->fileSystem->expects($this->once())
            ->method('file_put_contents')
            ->with('/tmp/temp_file', $this->isType('string'), LOCK_EX);

        $this->fileSystem->expects($this->once())
            ->method('rename')
            ->with('/tmp/temp_file', $this->stringContains($expectedFileName));

        $this->cache->writeArrayFileCache($data, $suffix);
    }

    public function testRuntimeCacheIsUsedForSubsequentReads(): void
    {
        $data = ['cached' => 'data'];
        $suffix = 'test';
        $expectedCacheFile = $this->getExpectedCacheFilePath($suffix);

        $this->fileSystem->method('file_exists')
            ->with($expectedCacheFile)
            ->willReturn(true);

        $this->fileSystem->expects($this->once())
            ->method('require')
            ->with($expectedCacheFile)
            ->willReturn($data);

        $result1 = $this->cache->readArrayFileCache($suffix);
        $result2 = $this->cache->readArrayFileCache($suffix);

        $this->assertSame($data, $result1);
        $this->assertSame($data, $result2);
    }


    public function testAtomicWriteOperationWithTempFile(): void
    {
        $data = ['test' => 'data'];
        $suffix = 'atomic';
        $expectedCacheFile = $this->getExpectedCacheFilePath($suffix);
        $expectedTempFile = '/tmp/shortnr_temp_123';

        $this->fileSystem->method('file_exists')
            ->willReturn(true);

        $this->fileSystem->method('tempnam')
            ->with(dirname($expectedCacheFile), ExtensionSetup::CACHE_KEY)
            ->willReturn($expectedTempFile);

        $this->fileSystem->expects($this->once())
            ->method('file_put_contents')
            ->with($expectedTempFile, $this->isType('string'), LOCK_EX);

        $this->fileSystem->expects($this->once())
            ->method('rename')
            ->with($expectedTempFile, $expectedCacheFile);

        $this->cache->writeArrayFileCache($data, $suffix);
    }

    private function getExpectedCacheFilePath(string $suffix): string
    {
        return sprintf(
            '%s/cache/code/%s/config%s.php',
            '/var/www/html/var',
            ExtensionSetup::CACHE_KEY,
            $suffix
        );
    }
}
