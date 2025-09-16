<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigLoader\YamlConfigSanitizer;
use CPSIT\ShortNr\Config\DTO\Config;
use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\PlatformAdapter\FileSystem\FileSystemInterface;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PathResolverInterface;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use TypedPatternEngine\Heuristic\PatternHeuristic;
use TypedPatternEngine\TypedPatternEngine;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class ConfigLoader
{
    private const DEFAULT_CONFIG_PATH = 'EXT:'. ExtensionSetup::EXT_KEY .'/Configuration/config.yaml';
    // Cache keys
    private const CONFIG_KEY = 'config';
    private const CONFIG_OBJ_KEY = 'configObj';
    private const HEURISTIC_KEY = 'heuristic';
    private readonly TypedPatternEngine $patternEngine;

    private array $runtimeCache = [];

    /**
     * @param CacheManager $cacheManager
     * @param ExtensionConfiguration $extensionConfiguration
     * @param FileSystemInterface $fileSystem
     * @param PathResolverInterface $pathResolver
     * @param YamlConfigSanitizer $yamlConfigSanitizer
     */
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly FileSystemInterface $fileSystem,
        private readonly PathResolverInterface $pathResolver,
        private readonly YamlConfigSanitizer $yamlConfigSanitizer,

    )
    {
        $this->patternEngine = new TypedPatternEngine();
    }


    /**
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function getConfig(): ConfigInterface
    {
        if (!isset($this->runtimeCache[self::CONFIG_OBJ_KEY])) {
            $configData = $this->getConfigArray(
                self::CONFIG_KEY,
                $this->buildCompleteConfig(...)
            );

            $patternCompiledName = ConfigEnum::Compiled->value;
            $patternDecompiledName = ConfigEnum::Decompiled->value;
            $compiler = $this->patternEngine->getPatternCompiler();
            foreach ($configData[$patternDecompiledName] ?? [] as $configName => $decompiledPatternData) {
                if (empty($decompiledPatternData)) {
                    continue;
                }
                $configData[$patternCompiledName][$configName] = $compiler->hydrate($decompiledPatternData);
                unset($configData[$patternDecompiledName]);
            }
            // create config
            $this->runtimeCache[self::CONFIG_OBJ_KEY] = new Config($configData);
        }

        return $this->runtimeCache[self::CONFIG_OBJ_KEY] ?? throw new ShortNrConfigException('Could not create Config Object');
    }

    /**
     * Get heuristic pattern checker for fast pre-filtering
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function getHeuristicPattern(): PatternHeuristic
    {
        if (!isset($this->runtimeCache[self::HEURISTIC_KEY])) {
            $compiler = $this->patternEngine->getHeuristicCompiler();
            $heuristicData = $this->getConfigArray(
                self::HEURISTIC_KEY,
                 fn(): array => $compiler->dehydrate(
                     $compiler->compile(
                         $this->getConfig()->getPatterns()
                     )
                 )
            );

            // rehydrate ast pattern heuristic data
            $this->runtimeCache[self::HEURISTIC_KEY] = $compiler->hydrate($heuristicData);
        }

        return $this->runtimeCache[self::HEURISTIC_KEY];
    }


    /**
     * Clear all caches
     */
    public function clearCache(): void
    {
        $this->cacheManager->getArrayFileCache()->invalidateCacheDirectory();
        $this->runtimeCache = [];
    }

    /**
     * Build complete configuration with AST compilation
     *
     * @return array
     * @throws ShortNrConfigException
     */
    private function buildCompleteConfig(): array
    {
        // Load and merge YAML configs
        $mergedConfig = $this->parseAndMergeConfigFiles();
        if (empty($mergedConfig)) {
            return [];
        }

        $patternCompiler =  $this->patternEngine->getPatternCompiler();
        $defConfigName = ConfigEnum::DEFAULT_CONFIG->value;
        $patternConfigName = ConfigEnum::Pattern->value;
        $configEntryName = ConfigEnum::ENTRYPOINT->value;
        $patternDecompiledName = ConfigEnum::Decompiled->value;
        foreach ($mergedConfig[$configEntryName] ?? [] as $configName => $configItemData) {
            if ($configName === $defConfigName || empty($configItemData[$patternConfigName])) {
                continue;
            }

            $compiledPattern = $patternCompiler->compile($configItemData[$patternConfigName]);
            $mergedConfig[$patternDecompiledName][$configName] = $patternCompiler->dehydrate($compiledPattern);
        }

        return $mergedConfig;
    }

    /**
     * Get config array with caching
     * @param string $prefix
     * @param callable $datasource
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    private function getConfigArray(string $prefix, callable $datasource): array
    {
        if (empty($prefix)) {
            throw new ShortNrConfigException('Config prefix cannot be empty');
        }

        $suffix = $prefix . '_' . $this->getYamlConfigFileSuffix();

        // Check cache validity
        if (!$this->isConfigCacheValid($suffix)) {
            $this->cacheManager->getArrayFileCache()->invalidateFileCache($suffix);
        } else {
            // Try to load from cache
            $cached = $this->cacheManager->getArrayFileCache()->readArrayFileCache($suffix);
            if ($cached !== null) {
                $this->runtimeCache[$prefix] = $cached;
                return $cached;
            }
        }

        // Generate fresh data
        if (!is_callable($datasource)) {
            throw new ShortNrConfigException('Config datasource must be callable');
        }

        $result = $datasource();
        if (!is_array($result)) {
            throw new ShortNrConfigException(
                'Config datasource must return an array, ' . gettype($result) . ' given.'
            );
        }

        // Allow empty arrays for patterns/heuristics when no patterns defined
        if (empty($result) && $prefix !== self::CONFIG_KEY) {
            $result = [];
        }

        // Cache the result
        $this->runtimeCache[$prefix] = $result;
        if (!empty($result) || $prefix === self::HEURISTIC_KEY || $prefix === ConfigEnum::Pattern->value) {
            $this->cacheManager->getArrayFileCache()->writeArrayFileCache($result, $suffix);
        }

        return $result;
    }

    /**
     * load and merge config also implement the Priority Value if not set
     *
     * @return array|null
     * @throws ShortNrConfigException
     */
    private function parseAndMergeConfigFiles(): ?array
    {
        $config = [];
        $priority = 0;
        $entryPointKey = ConfigEnum::ENTRYPOINT->value;
        $priorityKey = ConfigEnum::Priority->value;
        $defaultKey = ConfigEnum::DEFAULT_CONFIG->value;
        foreach ($this->getAllConfigurationFiles() as $file) {
            $subConfig = $this->parseYamlConfigFile($file);
            if (is_array($subConfig) && !empty($subConfig)) {
                // increase the priority of each SubConfig if not already set
                foreach ($subConfig[$entryPointKey]??[] as $subConfigKey => $subConfigValue) {
                    // skip default config
                    if ($subConfigKey === $defaultKey) {
                        continue;
                    }
                    $subConfig[$entryPointKey][$subConfigKey][$priorityKey] ??= $priority;
                }
                $config[] = $subConfig;
            }
            $priority++;
        }

        $mergedConfig = $config === [] ? null : array_replace_recursive(...$config);
        uasort($mergedConfig[$entryPointKey], fn(array $a, array $b) => ($b[$priorityKey] ?? PHP_INT_MIN) <=> ($a[$priorityKey] ?? PHP_INT_MIN));
        return $mergedConfig;
    }

    /**
     * @param string $configFile
     * @return array|null
     * @throws ShortNrConfigException
     */
    private function parseYamlConfigFile(string $configFile): ?array
    {
        if ($this->fileSystem->file_exists($configFile)) {
            $config = Yaml::parse($this->fileSystem->file_get_contents($configFile), Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
            // check if config is valid and entryPoint exists
            if (is_array($config) and !empty($config[ConfigEnum::ENTRYPOINT->value])) {
                return $this->yamlConfigSanitizer->sanitize($config);
            }
        }

        return null;
    }

    /**
     * @throws ShortNrConfigException
     */
    protected function isConfigCacheValid(string $suffix): bool
    {
        // is yaml file is more up to date then cache file ... it's considered not valid
        // we only run this check once per execution
        return ($this->runtimeCache['file']['valid'][$suffix] ??= (function(string $suffix): bool {

            // load all config files, user and default
            $cacheMTime = $this->cacheManager->getArrayFileCache()->getFileModificationTime($suffix);
            foreach ($this->getAllConfigurationFiles() as $configurationFile) {
                $yamlMTime = $this->fileSystem->filemtime($configurationFile);
                if ($yamlMTime === false || $cacheMTime === null) {
                    return false;
                }

                if ($yamlMTime > $cacheMTime) {
                    return false;
                }
            }
            // no anomalies found / seems valid (not outdated)
            return true;
        })($suffix)) ?? false;
    }

    /**
     * generates a hash based on the given config location
     *
     * @return string
     * @throws ShortNrConfigException
     */
    public function getYamlConfigFileSuffix(): string
    {
        return $this->runtimeCache['file']['hash'] ??= md5(implode(',', $this->getAllConfigurationFiles()));
    }

    /**
     * @return array
     * @throws ShortNrConfigException
     */
    private function getAllConfigurationFiles(): array
    {
        return $this->runtimeCache['file']['list'] = array_filter(array_unique([
            $this->getDefaultYamlConfigFilePath(),
            $this->getUserYamlConfigFilePath()
        ]));
    }

    /**
     * default config
     * @return string
     */
    private function getDefaultYamlConfigFilePath(): string
    {
        return $this->runtimeCache['file']['default_path'] ??= $this->processYamlConfigFilePath(
            self::DEFAULT_CONFIG_PATH
        );
    }

    /**
     * @return string|null
     * @throws ShortNrConfigException
     */
    private function getUserYamlConfigFilePath(): ?string
    {
        return $this->runtimeCache['file']['path'] ??= $this->processYamlConfigFilePath(
            $this->getTypo3Configuration()['configFile'] ?? null
        );
    }

    /**
     * process the user given Config from the Extension Configuration
     *
     * @param string|null $configFilePath
     * @return string|null
     */
    private function processYamlConfigFilePath(?string $configFilePath): ?string
    {
        if ($configFilePath) {
            return $this->prepareYamlConfigFilePath($configFilePath);
        }

        return null;
    }

    /**
     * @param string $path
     * @return string
     */
    private function prepareYamlConfigFilePath(string $path): string
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
