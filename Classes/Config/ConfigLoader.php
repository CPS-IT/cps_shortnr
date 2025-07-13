<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\PlatformAdapter\FileSystem\FileSystemInterface;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PathResolverInterface;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class ConfigLoader
{
    private static array $runtimeCache = [];

    /**
     * @param CacheManager $cacheManager
     * @param ExtensionConfiguration $extensionConfiguration
     * @param FileSystemInterface $fileSystem
     * @param PathResolverInterface $pathResolver
     */
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly FileSystemInterface $fileSystem,
        private readonly PathResolverInterface $pathResolver
    )
    {}

    /**
     * @return array
     * @throws ShortNrCacheException|ShortNrConfigException
     */
    public function getConfig(): array
    {
        $suffix = $this->getConfigFileSuffix();
        // cache file is outdated ... remove and process a fresh one
        if (!$this->isConfigCacheValid($suffix)) {
            $this->cacheManager->getArrayFileCache()->invalidateFileCache($suffix);
        } else {
            $config = $this->cacheManager->getArrayFileCache()->readArrayFileCache($suffix);
            if ($config)
                return $config;
        }

        $config = self::$runtimeCache['config'] ??= $this->parseYamlConfigFile($this->getConfigFilePath());
        if ($config) {
            $this->cacheManager->getArrayFileCache()->writeArrayFileCache($config, $suffix);
        }

        return $config ?? [];
    }

    /**
     * @param string|null $configFile
     * @return array|null
     */
    private function parseYamlConfigFile(?string $configFile): ?array
    {
        if ($configFile === null) {
            return null;
        }

        if ($this->fileSystem->file_exists($configFile)) {
            $config = Yaml::parse($this->fileSystem->file_get_contents($configFile));
            if (is_array($config)) {
                return $config;
            }
        }

        return null;
    }

    /**
     * @throws ShortNrConfigException
     */
    protected function isConfigCacheValid(string $suffix = ''): bool
    {
        // is yaml file is more up to date then cache file ... it's considered not valid
        // we only run this check once per execution
        return (self::$runtimeCache['file']['valid'] ??= (function(string $suffix): ?bool {
            $configPath = $this->getConfigFilePath();
            if ($configPath === null) {
                return null; // No config file configured
            }

            $yamlMTime = $this->fileSystem->filemtime($configPath);
            $cacheMTime = $this->cacheManager->getArrayFileCache()->getFileModificationTime($suffix);

            if ($yamlMTime === false || $cacheMTime === null) {
                return null;
            }

            return $yamlMTime <= $cacheMTime;
        })($suffix)) ?? false;
    }

    /**
     * generates a hash based on the given config location
     *
     * @return string
     * @throws ShortNrConfigException
     */
    public function getConfigFileSuffix(): string
    {
        return self::$runtimeCache['file']['hash'] ??= md5($this->getConfigFilePath() ?? '');
    }

    /**
     * @return string|null
     * @throws ShortNrConfigException
     */
    protected function getConfigFilePath(): ?string
    {
        return self::$runtimeCache['file']['path'] ??= $this->processConfigFilePath();
    }

    /**
     * @return string|null
     * @throws ShortNrConfigException
     */
    private function processConfigFilePath(): ?string
    {
        $configFile = $this->getTypo3Configuration();
        $configFilePath = $configFile['configFile'] ?? null;
        if ($configFilePath) {
            return $this->prepareConfigFilePath($configFilePath);
        }

        return null;
    }

    /**
     * @param string $path
     * @return string
     */
    private function prepareConfigFilePath(string $path): string
    {
        if (str_starts_with($path, 'FILE:')) {
            $path = str_replace('FILE:', '', $path);
        }

        return $this->pathResolver->getAbsolutePath($path);
    }

    /**
     * @throws ShortNrConfigException
     */
    private function getTypo3Configuration(): array
    {
        try {
            return $this->extensionConfiguration->get(ExtensionSetup::EXT_KEY);
        } catch (Throwable $e) {
            throw new ShortNrConfigException('Could not load Config for key: '. ExtensionSetup::EXT_KEY, previous: $e);
        }
    }
}
