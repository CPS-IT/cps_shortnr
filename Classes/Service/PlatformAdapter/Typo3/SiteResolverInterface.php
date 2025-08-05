<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\PlatformAdapter\Typo3;

use CPSIT\ShortNr\Exception\ShortNrSiteFinderException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;

/**
 * Acts as an adapter between typo3 framework and Extension
 *
 * enable easy testing and upgrade path for future typo3 versions
 */
interface SiteResolverInterface
{
    /**
     * Get Site and language-specific base URI for a page
     *
     * @param int $pageUid Page UID to resolve language for
     * @param int $languageId Language ID
     * @return string Language base URI (e.g., "/de", "/en", "/" "/base/en/")
     * @throws ShortNrSiteFinderException When site/language cannot be resolved
     */
    public function getSiteBaseUri(int $pageUid, int $languageId): string;
}
