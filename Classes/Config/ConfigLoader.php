<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\FileSystem\FileSystemInterface;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigLoader
{
    private static array $runtimeCache = [];

    /**
     * @param CacheManager $cacheManager
     * @param ExtensionConfiguration $extensionConfiguration
     * @param FileSystemInterface $fileSystem
     */
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly FileSystemInterface $fileSystem
    )
    {}

    /**
     * @return array
     * @throws ShortNrCacheException|ShortNrConfigException
     */
    public function getConfig(): array
    {
        // cache file is outdated ... remove and process a fresh one
        if (!$this->isConfigCacheValid()) {
            $this->cacheManager->getArrayFileCache()->invalidateFileCache();
        }

        $config = $this->cacheManager->getArrayFileCache()->readArrayFileCache();
        if ($config)
            return $config;

        $config = self::$runtimeCache['config'] ??= $this->parseYamlConfigFile($this->getConfigFilePath());
        if ($config) {
            $this->cacheManager->getArrayFileCache()->writeArrayFileCache($config);
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
    protected function isConfigCacheValid(): bool
    {
        // is yaml file is more up to date then cache file ... it's considered not valid
        // we only run this check once per execution
        return self::$runtimeCache['file']['valid'] ??= (function(): ?bool {
            $configPath = $this->getConfigFilePath();
            if ($configPath === null) {
                return null; // No config file configured
            }

            $yamlMTime = $this->fileSystem->filemtime($configPath);
            $cacheMTime = $this->cacheManager->getArrayFileCache()->getFileModificationTime();

            if ($yamlMTime === false || $cacheMTime === null) {
                return null;
            }

            return $yamlMTime <= $cacheMTime;
        })();
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

        return GeneralUtility::getFileAbsFileName($path);
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
