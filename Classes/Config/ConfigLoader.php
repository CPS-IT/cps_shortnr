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
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class ConfigLoader
{
    private const DEFAULT_CONFIG_PATH = 'EXT:'. ExtensionSetup::EXT_KEY .'/Configuration/config.yaml';
    private const COMPILED_REGEX_KEY = 'regex';
    private const CONFIG_KEY = 'config';
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
        private readonly YamlConfigSanitizer $yamlConfigSanitizer
    )
    {}

    /**
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function getConfig(): ConfigInterface
    {
        return $this->runtimeCache[self::CONFIG_KEY] ??= new Config(
            $this->getConfigArray(
                self::CONFIG_KEY,
                $this->parseAndMergeConfigFiles(...)
            )
        );
    }

    /**
     * @return void
     */
    public function clearCache(): void
    {
        $this->cacheManager->getArrayFileCache()->invalidateCacheDirectory();
    }

    /**
     * store the fast lookup regex into
     *
     * depends on getConfig()
     *
     * @return string|null
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function getCompiledRegexForFastCheck(): ?string
    {
        return $this->runtimeCache[self::COMPILED_REGEX_KEY] ??= $this->getConfigArray(
            self::COMPILED_REGEX_KEY,
            $this->compileYamlConfigRegex(...)
        )[self::COMPILED_REGEX_KEY] ?? null;
    }

    /**
     * compiled ALL configs into one regex for a fast lookup
     *
     * @return array<string, string>
     * @throws ShortNrConfigException
     */
    private function compileYamlConfigRegex(): array
    {
        $compiled = null;
        try {
            $regexList = array_keys($this->getConfig()->getUniqueRegexConfigNameGroup());
            $count = count($regexList);

            if ($count === 0) {
                return [];
            } elseif ($count === 1) {
                return [self::COMPILED_REGEX_KEY => $regexList[0]];
            }

            $parts = [];
            foreach ($regexList as $rx) {
                // 1. Split into delimiter, body, modifiers
                if (!preg_match('/^(.)(.*)\1([a-zA-Z]*)$/s', $rx, $m)) {
                    // Malformed pattern – skip or log; do NOT treat as literal
                    continue;
                }
                [, $delim, $body] = $m;
                // 2. Escape the delimiter only if it occurs inside the body
                $body = str_replace($delim, '\\' . $delim, $body);
                // 3. Wrap in non-capturing group to preserve alternation semantics
                $parts[] = "(?:$body)";
            }

            // 4. Re-assemble with a single set of delimiters and global modifiers
            $compiled = '/' . implode('|', $parts) . '/i';
        } catch (Throwable) {
            throw new ShortNrConfigException('Could not generate Compiled Regex Lookup String');
        }

        return [self::COMPILED_REGEX_KEY => $compiled];
    }

    /**
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

        $suffix = $prefix.'_'.$this->getYamlConfigFileSuffix();
        // cache file is outdated ... remove and process a fresh one
        if (!$this->isConfigCacheValid($suffix)) {
            $this->cacheManager->getArrayFileCache()->invalidateFileCache($suffix);
        } else {
            $config = $this->runtimeCache[$prefix] ??= $this->cacheManager->getArrayFileCache()->readArrayFileCache($suffix);
            if ($config)
                return $config;
        }

        if (!is_callable($datasource)) {
            throw new ShortNrConfigException('Config Datasource must be callable');
        }

        if (!is_array($result = $datasource()) && !empty($result)) {
            throw new ShortNrConfigException('Config Datasource must return an array that is not empty, '.gettype($result).' given.');
        }
        $config = $this->runtimeCache[$prefix] ??= $result;
        if ($config) {
            $this->cacheManager->getArrayFileCache()->writeArrayFileCache($config, $suffix);
        }

        return $config ?? [];
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
        // this list is mandatory since it also indicate if the List is empty for some reason
        $mergedConfig[Config::PREFIX_MAP_KEY] = $this->generateFastPrefixLookupMap($mergedConfig);
        $mergedConfig[Config::SORTED_REGEX_LIST_KEY] = $this->generateSortedRegexList($mergedConfig);
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
     * Generate a fast lookUp map that map all PREFIX (CASE INSENSITIVE) to the corresponding config Names
     *
     * @param array $configArray
     * @return array
     */
    private function generateFastPrefixLookupMap(array $configArray): array
    {
        // load default config
        $defaultConfigName = ConfigEnum::DEFAULT_CONFIG->value;
        $configPrefixKey = ConfigEnum::Prefix->value;
        $prefixMatch = ConfigEnum::PrefixMatch->value;
        $defaultConfig = $configArray[ConfigEnum::ENTRYPOINT->value][$defaultConfigName] ?? [];

        $lookupMap = [];
        foreach ($configArray[ConfigEnum::ENTRYPOINT->value] ?? [] as $configName => $configItemData) {
            // skip default config entry
            if ($configName === $defaultConfigName) {
                continue;
            }



            if (!empty($prefix = strtolower($configItemData[$configPrefixKey] ?? ''))) {
                // load match group for that prefix

                $prefixMatchValue = $configItemData[$prefixMatch] ?? $defaultConfig[$prefixMatch] ?? null;
                if (!empty($prefixMatchValue)) {
                    $lookupMap[$prefix] = ['name' => $configName, $prefixMatch => $prefixMatchValue];
                }
            }
        }

        return $lookupMap;
    }

    /**
     * Generate sorted regex list grouped by regex pattern, sorted by priority (high to low)
     *
     * @param array $configArray
     * @return array<string, array>
     */
    private function generateSortedRegexList(array $configArray): array
    {
        $defaultConfigName = ConfigEnum::DEFAULT_CONFIG->value;
        $entryPointKey = ConfigEnum::ENTRYPOINT->value;
        $regexKey = ConfigEnum::Regex->value;
        $priorityKey = ConfigEnum::Priority->value;
        $defaultConfig = $configArray[$entryPointKey][$defaultConfigName] ?? [];

        $configData = [];
        foreach ($configArray[$entryPointKey] ?? [] as $configName => $configItemData) {
            // skip default config entry
            if ($configName === $defaultConfigName) {
                continue;
            }

            $regex = $configItemData[$regexKey] ?? $defaultConfig[$regexKey] ?? null;
            if ($regex === null) {
                continue;
            }

            $priority = (int)($configItemData[$priorityKey] ?? 0);
            $configData[] = [
                'name' => $configName,
                'priority' => $priority,
                'regex' => $regex
            ];
        }

        // Sort by priority (high to low)
        usort($configData, fn($a, $b) => $b['priority'] <=> $a['priority']);

        // Build regex list grouped by regex pattern
        $regexNameList = [];
        foreach ($configData as $config) {
            $regexNameList[$config['regex']][] = $config['name'];
        }

        return $regexNameList;
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
