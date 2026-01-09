<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\PlatformAdapter\Typo3;

interface PathResolverInterface
{
    /**
     * Resolve a path to an absolute path according to TYPO3 conventions
     * 
     * This method handles:
     * - EXT: prefixes (e.g., "EXT:extension/path/file.txt")
     * - Relative paths within TYPO3 context
     * - FILE: prefixes
     * - Security restrictions (lockRootPath)
     * 
     * @param string $path The path to resolve
     * @return string The resolved absolute path
     */
    public function getAbsolutePath(string $path): string;
}
