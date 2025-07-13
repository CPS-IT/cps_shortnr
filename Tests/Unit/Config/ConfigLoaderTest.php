<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit\Config;

use CPSIT\ShortNr\Cache\CacheAdapter\FastArrayFileCache;
use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Config\DTO\Config;
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

    public function testGetConfigReturnsEmptyConfigWhenNoConfigFileConfigured(): void
    {
        $this->extensionConfiguration->method('get')
            ->with(ExtensionSetup::EXT_KEY)
            ->willReturn([]);
        
        $result = $this->configLoader->getConfig();
        
        $this->assertInstanceOf(ConfigInterface::class, $result);
        $this->assertSame([], $result->getConfigNames());
    }

    public function testGetConfigReturnsCachedDataWhenCacheIsValid(): void
    {
        $configFilePath = '/path/to/config.yaml';
        $configHash = md5($configFilePath);
        $cachedData = ['shortnr' => ['pages' => ['type' => 'page']]];
        
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
        
        $this->assertInstanceOf(ConfigInterface::class, $result);
        $this->assertSame(['pages'], $result->getConfigNames());
    }

    public function testGetConfigInvalidatesCacheWhenYamlIsNewer(): void
    {
        $configFilePath = '/path/to/config.yaml';
        $configHash = md5($configFilePath);
        $yamlContent = "shortnr:\n  pages:\n    type: page\n";
        $expectedConfig = ['shortnr' => ['pages' => ['type' => 'page']]];
        
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
        
        $this->assertInstanceOf(ConfigInterface::class, $result);
        $this->assertSame(['pages'], $result->getConfigNames());
    }

    public static function yamlContentDataProvider(): array
    {
        return [
            'simple config' => [
                'yamlContent' => "shortnr:\n  pages:\n    type: page\n",
                'expectedConfigNames' => ['pages']
            ],
            'nested config' => [
                'yamlContent' => "shortnr:\n  pages:\n    type: page\n  articles:\n    type: plugin\n",
                'expectedConfigNames' => ['pages', 'articles']
            ],
            'config with default' => [
                'yamlContent' => "shortnr:\n  _default:\n    regex: '/test/'\n  pages:\n    type: page\n",
                'expectedConfigNames' => ['pages']
            ],
            'empty yaml' => [
                'yamlContent' => "",
                'expectedConfigNames' => []
            ]
        ];
    }

    /**
     * @dataProvider yamlContentDataProvider
     */
    public function testGetConfigParsesYamlContentCorrectly(string $yamlContent, array $expectedConfigNames): void
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
        
        $this->fastArrayFileCache->method('writeArrayFileCache')
            ->with($this->isType('array'), $configHash);
        
        $result = $this->configLoader->getConfig();
        
        $this->assertInstanceOf(ConfigInterface::class, $result);
        $this->assertSame($expectedConfigNames, $result->getConfigNames());
    }



    public function testGetConfigHandlesFilePathWithPrefix(): void
    {
        $configFilePath = 'FILE:/path/to/config.yaml';
        $resolvedPath = '/path/to/config.yaml';
        $yamlContent = "shortnr:\n  pages:\n    type: page\n";
        
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
            ->with($this->isType('array'), md5($resolvedPath));
        
        $result = $this->configLoader->getConfig();
        
        $this->assertInstanceOf(ConfigInterface::class, $result);
        $this->assertSame(['pages'], $result->getConfigNames());
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
        $yamlContent = "shortnr:\n  pages:\n    type: page\n";
        
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
            ->with($this->isType('array'), $this->isType('string'));
        
        $result1 = $this->configLoader->getConfig();
        $result2 = $this->configLoader->getConfig();
        
        $this->assertInstanceOf(ConfigInterface::class, $result1);
        $this->assertInstanceOf(ConfigInterface::class, $result2);
        $this->assertSame(['pages'], $result1->getConfigNames());
        $this->assertSame(['pages'], $result2->getConfigNames());
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

    public function testGetConfigReturnsEmptyConfigWhenYamlFileDoesNotExist(): void
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
        
        $this->assertInstanceOf(ConfigInterface::class, $result);
        $this->assertSame([], $result->getConfigNames());
    }

}
