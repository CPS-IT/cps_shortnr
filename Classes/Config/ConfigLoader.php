<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\Ast\Heuristic\HeuristicPatternInterface;
use CPSIT\ShortNr\Config\Ast\PatternBuilder;
use CPSIT\ShortNr\Config\Ast\Types\TypeRegistry;
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
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class ConfigLoader
{
    private const DEFAULT_CONFIG_PATH = 'EXT:'. ExtensionSetup::EXT_KEY .'/Configuration/config.yaml';
    // Cache keys
    private const CONFIG_KEY = 'config';
    private const HEURISTIC_KEY = 'heuristic';
    private const PATTERNS_KEY = 'patterns';
    private array $runtimeCache = [];
    private ?PatternBuilder $patternBuilder = null;
    private ?TypeRegistry $astTypeRegistry = null;

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
        private readonly YamlConfigSanitizer $yamlConfigSanitizer
    )
    {}

    /**
     * @param TypeRegistry|null $typeRegistry
     * @return void
     */
    public function setPatternTypeRegistry(?TypeRegistry $typeRegistry): void
    {
        $this->astTypeRegistry = $typeRegistry;
    }


    /**
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function getConfig(): ConfigInterface
    {
        if (!isset($this->runtimeCache[self::CONFIG_KEY])) {
            $configData = $this->getConfigArray(
                self::CONFIG_KEY,
                $this->buildCompleteConfig(...)
            );

            // create object from cache
            $builder = $this->getPatternBuilder();
            foreach ($configData[ConfigInterface::COMPILED_PATTERN_KEY] ?? [] as $configName => $astData) {
                $configData[ConfigInterface::COMPILED_PATTERN_KEY][$configName] = $builder->getPatternCompiler()->hydrate($astData);
            }
            $this->runtimeCache[self::CONFIG_KEY] = new Config($configData);
        }

        return $this->runtimeCache[self::CONFIG_KEY];
    }

    /**
     * Get heuristic pattern checker for fast pre-filtering
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function getHeuristicPattern(): HeuristicPatternInterface
    {
        if (!isset($this->runtimeCache[self::HEURISTIC_KEY])) {
            $heuristicData = $this->getConfigArray(
                self::HEURISTIC_KEY,
                 $this->buildHeuristicData(...)
            );

            $this->runtimeCache[self::HEURISTIC_KEY] = $this->getPatternBuilder()->getHeuristicCompiler()->hydrate($heuristicData);
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
        $this->patternBuilder = null;
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

        // Extract and compile patterns
        $patterns = $this->extractPatternsFromConfig($mergedConfig);
        $builder = $this->getPatternBuilder();
        $compiled = [];

        foreach ($patterns as $configName => $pattern) {
            try {
                $compiledPattern = $builder->getPatternCompiler()->compile($pattern);
                $compiled[$configName] = $builder->getPatternCompiler()->dehydrate($compiledPattern);

            } catch (Throwable $e) {
                // Log compilation error and skip this pattern
                // You might want to add proper logging here
                error_log("Failed to compile pattern for '$configName': " . $e->getMessage());
                continue;
            }
        }

        // Store compilation info
        $mergedConfig[ConfigInterface::COMPILED_PATTERN_KEY] = $compiled;
        return $mergedConfig;
    }

    /**
     * Build complete configuration with AST compilation
     *
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    private function buildHeuristicData(): array
    {
        // Build heuristic from AST analysis
        $builder = $this->getPatternBuilder();
        $heuristic = $builder->getHeuristicCompiler()->compile(
            $this->getConfig()->getPatterns()
        );
        return $builder->getHeuristicCompiler()->dehydrate($heuristic);
    }

    /**
     * Extract pattern definitions from config
     *
     * @param array $config
     * @return array<string, string> [configName => "pattern"]
     * @throws ShortNrConfigException
     */
    private function extractPatternsFromConfig(array $config): array
    {
        $patterns = [];
        $entryPointKey = ConfigEnum::ENTRYPOINT->value;
        $defaultConfigKey = ConfigEnum::DEFAULT_CONFIG->value;

        foreach ($config[$entryPointKey] ?? [] as $configName => $configData) {
            if ($configName === $defaultConfigKey) {
                continue;
            }

            $pattern = $configData[ConfigEnum::Pattern->value] ?? null;
            if ($pattern !== null) {
                // Add validation here, if not valid throw exception
                if (!$this->isValidAstPattern($pattern)) {
                    throw new ShortNrConfigException('Pattern without any groups are not allowed: ' . $pattern);
                }

                $patterns[$configName] = $pattern;
            }
        }

        return $patterns;
    }

    /**
     * Basic validation that a string looks like an AST pattern
     *
     * @param string $pattern
     * @return bool
     */
    private function isValidAstPattern(string $pattern): bool
    {
        // Must contain at least one group definition {name:type}
        // or be a simple literal pattern
        if (str_contains($pattern, '{')) {
            return preg_match('/\{[a-zA-Z_][a-zA-Z0-9_]*:[a-zA-Z]+/', $pattern) === 1;
        }

        return false;
    }

    /**
     * Get or create pattern builder instance
     * @return PatternBuilder
     */
    private function getPatternBuilder(): PatternBuilder
    {
        return $this->patternBuilder ??= new PatternBuilder($this->astTypeRegistry ??= new TypeRegistry());
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
        if (!empty($result) || $prefix === self::HEURISTIC_KEY || $prefix === self::PATTERNS_KEY) {
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
