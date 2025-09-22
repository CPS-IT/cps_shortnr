<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigLoader\YamlConfigSanitizer;
use CPSIT\ShortNr\Config\DTO\Config;
use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
use CPSIT\ShortNr\Event\ShortNrConfigLoadedEvent;
use CPSIT\ShortNr\Event\ShortNrConfigPathEvent;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\PlatformAdapter\FileSystem\FileSystemInterface;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PathResolverInterface;
use CPSIT\ShortNr\Traits\ArrayPackTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Yaml\Yaml;
use TypedPatternEngine\Heuristic\PatternHeuristic;
use TypedPatternEngine\TypedPatternEngine;

class ConfigLoader
{
    use ArrayPackTrait;

    // Cache keys
    private const CONFIG_KEY = 'config';
    private const CONFIG_OBJ_KEY = 'configObj';
    private const HEURISTIC_KEY = 'heuristic';
    private readonly TypedPatternEngine $patternEngine;

    private array $runtimeCache = [];

    /**
     * @param CacheManager $cacheManager
     * @param FileSystemInterface $fileSystem
     * @param PathResolverInterface $pathResolver
     * @param YamlConfigSanitizer $yamlConfigSanitizer
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly FileSystemInterface $fileSystem,
        private readonly PathResolverInterface $pathResolver,
        private readonly YamlConfigSanitizer $yamlConfigSanitizer,
        private readonly EventDispatcherInterface $eventDispatcher
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
        // remove all NULL values
        $mergedConfig = $this->reconstructFlattenArrayKeyPath(
            array_filter(
                $this->flattenArrayKeyPath(
                    $this->parseAndMergeConfigFiles()
                ),
                fn(mixed $item): bool => $item !== null)
        );

        if (empty($mergedConfig)) {
            return [];
        }

        // Dispatch event to allow config manipulation
        $event = new ShortNrConfigLoadedEvent($mergedConfig);
        $this->eventDispatcher->dispatch($event);
        $mergedConfig = $event->getConfiguration();

        $patternCompiler =  $this->patternEngine->getPatternCompiler();
        $defConfigName = ConfigEnum::DEFAULT_CONFIG->value;
        $patternConfigName = ConfigEnum::Pattern->value;
        $configEntryName = ConfigEnum::ENTRYPOINT->value;
        $patternDecompiledName = ConfigEnum::Decompiled->value;

        // compile the pattern
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
        $configs = [];
        $entryPointKey = ConfigEnum::ENTRYPOINT->value;
        foreach ($this->getAllConfigurationFiles() as $file) {
            $subConfig = $this->parseYamlConfigFile($file);
            if (!empty($subConfig[$entryPointKey]) && is_array($subConfig[$entryPointKey])) {
                $configs[] = $subConfig;
            }
        }
        if (count($configs) > 1) {
            return array_replace_recursive(...$configs);
        }

        return $configs[0]??[];
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
        // is at least one yaml file is more up to date then cache file ... it's considered not valid
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
    private function getYamlConfigFileSuffix(): string
    {
        return $this->runtimeCache['file']['hash'] ??= md5(implode(',', $this->getAllConfigurationFiles()));
    }

    /**
     * @return array
     * @throws ShortNrConfigException
     */
    private function getAllConfigurationFiles(): array
    {
        if (isset($this->runtimeCache['file']['list'])) {
            return $this->runtimeCache['file']['list'];
        }

        // Dispatch event to collect additional config paths
        $event = new ShortNrConfigPathEvent();
        $this->eventDispatcher->dispatch($event);

        // Add paths from event
        $configPaths = [];
        foreach ($event->getConfigPaths() as $path) {
            $processedPath = $this->processYamlConfigFilePath($path);
            if ($processedPath !== null) {
                $configPaths[] = $processedPath;
            }
        }

        $configPaths = array_filter(array_unique($configPaths));
        if (empty($configPaths)) {
            throw new ShortNrConfigException('No config found, please add a valid config via ' . ShortNrConfigPathEvent::class);
        }
        return $this->runtimeCache['file']['list'] = $configPaths;
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
}
