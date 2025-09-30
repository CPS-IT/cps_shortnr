<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Cache\CacheAdapter;

use CPSIT\ShortNr\Config\ExtensionSetup;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Service\PlatformAdapter\FileSystem\FileSystemInterface;
use Symfony\Component\Filesystem\Path;
use Throwable;
use TYPO3\CMS\Core\Core\Environment;

class FastArrayFileCache
{
    private array $runtimeCache = [];

    private const FILE_NAME = 'config%s.php';

    public function __construct(
        private readonly FileSystemInterface $fileSystem
    )
    {}

    /**
     * @param string $suffix
     * @return array|null
     * @throws ShortNrCacheException
     */
    public function readArrayFileCache(string $suffix): ?array
    {
        $suffix = $this->sanitizeSuffix($suffix);
        return $this->runtimeCache['data'][$suffix] ??= $this->fetchDataFromFileCache($suffix);
    }

    /**
     * @param array $data
     * @param string $suffix
     * @return void
     * @throws ShortNrCacheException
     */
    public function writeArrayFileCache(array $data, string $suffix): void
    {
        $suffix = $this->sanitizeSuffix($suffix);
        $this->runtimeCache['data'][$suffix] = $data;
        $cacheFile = $this->getArrayCacheFilePath($suffix);
        $this->ensureCacheDirectoryExists($cacheFile);
        $this->writeArrayToFile($data, $cacheFile);
    }

    /**
     * @param string $cacheFile
     * @return void
     * @throws ShortNrCacheException
     */
    private function ensureCacheDirectoryExists(string $cacheFile): void
    {
        $cacheDir = Path::getDirectory($cacheFile);

        if (!$this->createDirIfNotExists($cacheDir)) {
            throw new ShortNrCacheException('Could not create dir: ' . $cacheDir);
        }
    }

    /**
     * @param array $data
     * @param string $cacheFile
     * @return void
     * @throws ShortNrCacheException
     */
    private function writeArrayToFile(array $data, string $cacheFile): void
    {
        $cacheDir = Path::getDirectory($cacheFile);
        $tempFile = $this->fileSystem->tempnam($cacheDir, ExtensionSetup::CACHE_KEY);
        $phpCode = $this->generatePhpArrayCode($data);

        try {
            $this->fileSystem->file_put_contents($tempFile, $phpCode, LOCK_EX);
            $this->fileSystem->rename($tempFile, $cacheFile);
        } catch (Throwable $e) {
            $this->fileSystem->unlink($tempFile);
            throw new ShortNrCacheException('Could not write Cache: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array $data
     * @return string
     */
    private function generatePhpArrayCode(array $data): string
    {
        return "<?php" . PHP_EOL . PHP_EOL . "return " . var_export($data, true) . ";" . PHP_EOL;
    }

    /**
     * @param string $suffix
     * @return array|null
     */
    private function fetchDataFromFileCache(string $suffix): ?array
    {
        $cacheFileLocation = $this->getArrayCacheFilePath($suffix);
        if (!$this->fileSystem->file_exists($cacheFileLocation)) {
            return null;
        }

        try {
            $result = $this->fileSystem->require($cacheFileLocation);
            return is_array($result) ? $result : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * empty cache and remove file
     *
     * @param string $suffix
     * @return void
     * @throws ShortNrCacheException
     */
    public function invalidateFileCache(string $suffix): void
    {
        $suffix = $this->sanitizeSuffix($suffix);
        unset($this->runtimeCache['data'][$suffix]);
        $cacheFileLocation = $this->getArrayCacheFilePath($suffix);
        if ($this->fileSystem->file_exists($cacheFileLocation)) {
            $this->fileSystem->unlink($cacheFileLocation);
        }
    }

    /**
     * destroy all files inside the own cache dir
     *
     * @return void
     */
    public function invalidateCacheDirectory(): void
    {
        $dir = $this->getFileCacheDirLocationString();
        if ($this->fileSystem->file_exists($dir)) {
            foreach ($this->fileSystem->scandir($dir) as $filePah) {
                if('.' === $filePah || '..' === $filePah) {
                    continue;
                }

                $fullPath = Path::join($dir, $filePah);
                if ($this->fileSystem->is_file($fullPath)) {
                    $this->fileSystem->unlink($fullPath);
                }
            }
        }
    }

    /**
     * @param string $suffix
     * @return int|null
     * @throws ShortNrCacheException
     */
    public function getFileModificationTime(string $suffix): ?int
    {
        $suffix = $this->sanitizeSuffix($suffix);
        $mtime = $this->fileSystem->filemtime($this->getArrayCacheFilePath($suffix));
        if($mtime === false){
            return null;
        }

        return $mtime;
    }

    /**
     * @param string $suffix
     * @return string
     */
    private function getArrayCacheFilePath(string $suffix): string
    {
        return $this->runtimeCache['path']['cacheArrayFile'][$suffix] ??= Path::join($this->getFileCacheDirLocationString(), sprintf(self::FILE_NAME, $suffix));
    }

    /**
     * @return string
     */
    protected function getFileCacheDirLocationString(): string
    {
        return $this->runtimeCache['path']['cacheDir'] ??= Path::join(
            Environment::getVarPath(),
            'cache',
            'code',
            ExtensionSetup::CACHE_KEY
        );
    }

    /**
     * @param string $dirPath
     * @return bool
     */
    private function createDirIfNotExists(string $dirPath): bool
    {
        if (!$this->fileSystem->file_exists($dirPath)) {
            return $this->fileSystem->mkdir($dirPath, 0755, true);
        }

        return true;
    }

    /**
     * @param string $suffix
     * @return string
     * @throws ShortNrCacheException
     */
    private function sanitizeSuffix(string $suffix): string
    {
        $suffix = trim(preg_replace('/[^a-zA-Z0-9_-]/', '_', $suffix));
        if (empty($suffix) || preg_match('/^_+$/', $suffix)) {
            throw new ShortNrCacheException('Cache suffix cannot be empty or contain only special characters');
        }

        return $suffix;
    }
}
