<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Path;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class Typo3PathResolver implements PathResolverInterface
{
    /**
     * Resolve a path to an absolute path according to TYPO3 conventions
     * 
     * @param string $path The path to resolve
     * @return string The resolved absolute path
     */
    public function getAbsolutePath(string $path): string
    {
        return GeneralUtility::getFileAbsFileName($path);
    }
}