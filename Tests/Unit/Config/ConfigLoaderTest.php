<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Config;

use CPSIT\ShortNr\Cache\CacheAdapter\FastArrayFileCache;
use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Config\ExtensionSetup;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\PlatformAdapter\FileSystem\FileSystemInterface;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PathResolverInterface;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class ConfigLoaderTest extends TestCase
{
    private ConfigLoader $configLoader;
    private CacheManager $cacheManager;
    private FastArrayFileCache $fastArrayFileCache;
    private ExtensionConfiguration $extensionConfiguration;
    private FileSystemInterface $fileSystem;
    private PathResolverInterface $pathResolver;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fileSystem = $this->createMock(FileSystemInterface::class);
        $this->fastArrayFileCache = $this->createMock(FastArrayFileCache::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $this->pathResolver = $this->createMock(PathResolverInterface::class);
        
        $this->cacheManager->method('getArrayFileCache')
            ->willReturn($this->fastArrayFileCache);
        
        // Mock path resolver to return the same path for simplicity
        $this->pathResolver->method('getAbsolutePath')
            ->willReturnCallback(function($path) {
                return $path;
            });
        
        $this->configLoader = new ConfigLoader(
            $this->cacheManager,
            $this->extensionConfiguration,
            $this->fileSystem,
            $this->pathResolver
        );
        
        $this->clearStaticRuntimeCache();
    }

    protected function tearDown(): void
    {
        $this->clearStaticRuntimeCache();
        parent::tearDown();
    }

    private function clearStaticRuntimeCache(): void
    {
        $reflection = new \ReflectionClass(ConfigLoader::class);
        $property = $reflection->getProperty('runtimeCache');
        $property->setValue(null, []);
    }

    public function testGetConfigReturnsEmptyArrayWhenNoConfigFileConfigured(): void
    {
        $this->extensionConfiguration->method('get')
            ->with(ExtensionSetup::EXT_KEY)
            ->willReturn([]);
        
        $result = $this->configLoader->getConfig();
        
        $this->assertSame([], $result);
    }

    public function testGetConfigReturnsCachedDataWhenCacheIsValid(): void
    {
        $configFilePath = '/path/to/config.yaml';
        $configHash = md5($configFilePath);
        $cachedData = ['cached' => 'data'];
        
        $this->extensionConfiguration->method('get')
            ->with(ExtensionSetup::EXT_KEY)
            ->willReturn(['configFile' => $configFilePath]);
        
        $this->fileSystem->method('filemtime')
            ->with($configFilePath)
            ->willReturn(1000);
        
        $this->fastArrayFileCache->method('getFileModificationTime')
            ->with($configHash)
            ->willReturn(2000);
        
        $this->fastArrayFileCache->method('readArrayFileCache')
            ->with($configHash)
            ->willReturn($cachedData);
        
        $result = $this->configLoader->getConfig();
        
        $this->assertSame($cachedData, $result);
    }

    public function testGetConfigInvalidatesCacheWhenYamlIsNewer(): void
    {
        $configFilePath = '/path/to/config.yaml';
        $configHash = md5($configFilePath);
        $yamlContent = "test: value\n";
        $expectedConfig = ['test' => 'value'];
        
        $this->extensionConfiguration->method('get')
            ->with(ExtensionSetup::EXT_KEY)
            ->willReturn(['configFile' => $configFilePath]);
        
        $this->fileSystem->method('filemtime')
            ->with($configFilePath)
            ->willReturn(2000);
        
        $this->fastArrayFileCache->method('getFileModificationTime')
            ->with($configHash)
            ->willReturn(1000);
        
        $this->fastArrayFileCache->expects($this->once())
            ->method('invalidateFileCache')
            ->with($configHash);
        
        $this->fastArrayFileCache->method('readArrayFileCache')
            ->with($configHash)
            ->willReturn(null);
        
        $this->fileSystem->method('file_exists')
            ->with($configFilePath)
            ->willReturn(true);
        
        $this->fileSystem->method('file_get_contents')
            ->with($configFilePath)
            ->willReturn($yamlContent);
        
        $this->fastArrayFileCache->expects($this->once())
            ->method('writeArrayFileCache')
            ->with($expectedConfig, $configHash);
        
        $result = $this->configLoader->getConfig();
        
        $this->assertSame($expectedConfig, $result);
    }

    public static function yamlContentDataProvider(): array
    {
        return [
            'simple config' => [
                'yamlContent' => "key: value\n",
                'expectedConfig' => ['key' => 'value']
            ],
            'nested config' => [
                'yamlContent' => "database:\n  host: localhost\n  port: 3306\n",
                'expectedConfig' => ['database' => ['host' => 'localhost', 'port' => 3306]]
            ],
            'array config' => [
                'yamlContent' => "features:\n  - feature1\n  - feature2\n",
                'expectedConfig' => ['features' => ['feature1', 'feature2']]
            ],
            'empty yaml' => [
                'yamlContent' => "",
                'expectedConfig' => []
            ]
        ];
    }

    /**
     * @dataProvider yamlContentDataProvider
     */
    public function testGetConfigParsesYamlContentCorrectly(string $yamlContent, array $expectedConfig): void
    {
        $configFilePath = '/path/to/config.yaml';
        $configHash = md5($configFilePath);
        
        $this->extensionConfiguration->method('get')
            ->with(ExtensionSetup::EXT_KEY)
            ->willReturn(['configFile' => $configFilePath]);
        
        $this->fileSystem->method('filemtime')
            ->with($configFilePath)
            ->willReturn(1000);
        
        $this->fastArrayFileCache->method('getFileModificationTime')
            ->with($configHash)
            ->willReturn(null);
        
        $this->fastArrayFileCache->method('readArrayFileCache')
            ->with($configHash)
            ->willReturn(null);
        
        $this->fileSystem->method('file_exists')
            ->with($configFilePath)
            ->willReturn(true);
        
        $this->fileSystem->method('file_get_contents')
            ->with($configFilePath)
            ->willReturn($yamlContent);
        
        if (!empty($expectedConfig)) {
            $this->fastArrayFileCache->expects($this->once())
                ->method('writeArrayFileCache')
                ->with($expectedConfig, $configHash);
        }
        
        $result = $this->configLoader->getConfig();
        
        $this->assertSame($expectedConfig, $result);
    }



    public function testGetConfigHandlesFilePathWithPrefix(): void
    {
        $configFilePath = 'FILE:/path/to/config.yaml';
        $resolvedPath = '/path/to/config.yaml';
        $expectedConfig = ['test' => 'value'];
        $yamlContent = "test: value\n";
        
        $this->extensionConfiguration->method('get')
            ->with(ExtensionSetup::EXT_KEY)
            ->willReturn(['configFile' => $configFilePath]);
        
        $this->pathResolver->method('getAbsolutePath')
            ->with('/path/to/config.yaml') // FILE: prefix should be removed
            ->willReturn($resolvedPath);
        
        $this->fileSystem->method('filemtime')
            ->with($resolvedPath)
            ->willReturn(1000);
        
        $this->fastArrayFileCache->method('getFileModificationTime')
            ->with(md5($resolvedPath))
            ->willReturn(null);
        
        $this->fastArrayFileCache->method('readArrayFileCache')
            ->with(md5($resolvedPath))
            ->willReturn(null);
        
        $this->fileSystem->method('file_exists')
            ->with($resolvedPath)
            ->willReturn(true);
        
        $this->fileSystem->method('file_get_contents')
            ->with($resolvedPath)
            ->willReturn($yamlContent);
        
        $this->fastArrayFileCache->expects($this->once())
            ->method('writeArrayFileCache')
            ->with($expectedConfig, md5($resolvedPath));
        
        $result = $this->configLoader->getConfig();
        
        $this->assertSame($expectedConfig, $result);
    }

    public function testGetConfigThrowsExceptionWhenExtensionConfigurationFails(): void
    {
        $this->extensionConfiguration->method('get')
            ->with(ExtensionSetup::EXT_KEY)
            ->willThrowException(new \Exception('Extension config error'));
        
        $this->expectException(ShortNrConfigException::class);
        $this->expectExceptionMessage('Could not load Config for key: ' . ExtensionSetup::EXT_KEY);
        
        $this->configLoader->getConfig();
    }

    public function testGetConfigUsesRuntimeCacheForSubsequentCalls(): void
    {
        $configFilePath = '/path/to/config.yaml';
        $expectedConfig = ['cached' => 'data'];
        $yamlContent = "cached: data\n";
        
        $this->extensionConfiguration->method('get')
            ->with(ExtensionSetup::EXT_KEY)
            ->willReturn(['configFile' => $configFilePath]);
        
        $this->fileSystem->method('filemtime')
            ->with($configFilePath)
            ->willReturn(1000);
        
        $this->fastArrayFileCache->method('getFileModificationTime')
            ->willReturn(null);
        
        $this->fastArrayFileCache->method('readArrayFileCache')
            ->willReturn(null);
        
        $this->fileSystem->method('file_exists')
            ->with($configFilePath)
            ->willReturn(true);
        
        $this->fileSystem->expects($this->once())
            ->method('file_get_contents')
            ->with($configFilePath)
            ->willReturn($yamlContent);
        
        $this->fastArrayFileCache->method('writeArrayFileCache')
            ->with($expectedConfig, $this->isType('string'));
        
        $result1 = $this->configLoader->getConfig();
        $result2 = $this->configLoader->getConfig();
        
        $this->assertSame($expectedConfig, $result1);
        $this->assertSame($expectedConfig, $result2);
    }

    public function testGetConfigFileSuffixReturnsHashOfConfigPath(): void
    {
        $configFilePath = '/path/to/config.yaml';
        $expectedHash = md5($configFilePath);
        
        $this->extensionConfiguration->method('get')
            ->with(ExtensionSetup::EXT_KEY)
            ->willReturn(['configFile' => $configFilePath]);
        
        $result = $this->configLoader->getConfigFileSuffix();
        
        $this->assertSame($expectedHash, $result);
    }

    public function testGetConfigFileSuffixReturnsEmptyHashWhenNoConfigFile(): void
    {
        $expectedHash = md5('');
        
        $this->extensionConfiguration->method('get')
            ->with(ExtensionSetup::EXT_KEY)
            ->willReturn([]);
        
        $result = $this->configLoader->getConfigFileSuffix();
        
        $this->assertSame($expectedHash, $result);
    }

    public function testGetConfigReturnsEmptyArrayWhenYamlFileDoesNotExist(): void
    {
        $configFilePath = '/path/to/nonexistent.yaml';
        $configHash = md5($configFilePath);
        
        $this->extensionConfiguration->method('get')
            ->with(ExtensionSetup::EXT_KEY)
            ->willReturn(['configFile' => $configFilePath]);
        
        $this->fileSystem->method('filemtime')
            ->with($configFilePath)
            ->willReturn(false);
        
        $this->fastArrayFileCache->method('getFileModificationTime')
            ->with($configHash)
            ->willReturn(null);
        
        $this->fastArrayFileCache->method('readArrayFileCache')
            ->with($configHash)
            ->willReturn(null);
        
        $this->fileSystem->method('file_exists')
            ->with($configFilePath)
            ->willReturn(false);
        
        $this->fastArrayFileCache->expects($this->never())
            ->method('writeArrayFileCache');
        
        $result = $this->configLoader->getConfig();
        
        $this->assertSame([], $result);
    }

}
