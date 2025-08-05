<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Config;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigLoader\YamlConfigSanitizer;
use CPSIT\ShortNr\Config\DTO\Config;
use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\PlatformAdapter\FileSystem\FileSystemInterface;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PathResolverInterface;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class ConfigLoader
{
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
                fn(): array => $this->parseYamlConfigFile($this->getYamlConfigFilePath())
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
     * @param string|null $configFile
     * @return array|null
     * @throws ShortNrConfigException
     */
    private function parseYamlConfigFile(?string $configFile): ?array
    {
        if ($configFile === null) {
            return null;
        }

        if ($this->fileSystem->file_exists($configFile)) {
            $config = Yaml::parse($this->fileSystem->file_get_contents($configFile), Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
            if (is_array($config)) {
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
        return ($this->runtimeCache['file']['valid'] ??= (function(string $suffix): ?bool {
            $yamlConfigPath = $this->getYamlConfigFilePath();
            if ($yamlConfigPath === null) {
                return null; // No config file configured
            }

            $yamlMTime = $this->fileSystem->filemtime($yamlConfigPath);
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
    public function getYamlConfigFileSuffix(): string
    {
        return $this->runtimeCache['file']['hash'] ??= md5($this->getYamlConfigFilePath() ?? '');
    }

    /**
     * @return string|null
     * @throws ShortNrConfigException
     */
    protected function getYamlConfigFilePath(): ?string
    {
        return $this->runtimeCache['file']['path'] ??= $this->processYamlConfigFilePath();
    }

    /**
     * @return string|null
     * @throws ShortNrConfigException
     */
    private function processYamlConfigFilePath(): ?string
    {
        $configFile = $this->getTypo3Configuration();
        $configFilePath = $configFile['configFile'] ?? null;
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
