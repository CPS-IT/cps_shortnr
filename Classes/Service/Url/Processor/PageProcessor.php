<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Exception\ShortNrSiteFinderException;
use CPSIT\ShortNr\Service\DataProvider\DTO\PageData;
use CPSIT\ShortNr\Service\DataProvider\PageDataProvider;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\SiteResolver;
use CPSIT\ShortNr\Service\Url\Condition\DTO\ConfigMatchCandidate;
use CPSIT\ShortNr\Service\Url\Processor\DTO\ProcessorDecodeResult;
use CPSIT\ShortNr\Service\Url\Processor\DTO\ProcessorDecodeResultInterface;
use Symfony\Component\Filesystem\Path;

class PageProcessor implements ProcessorInterface
{
    public function __construct(
        protected readonly PageDataProvider $pageDataProvider,
        protected readonly SiteResolver $siteResolver
    )
    {}

    /**
     * @return string
     */
    public function getType(): string
    {
        return 'page';
    }

    /**
     * @param ConfigMatchCandidate $candidate
     * @param ConfigItemInterface $config
     * @return ProcessorDecodeResultInterface
     * @throws ShortNrSiteFinderException
     * @throws ShortNrNotFoundException
     */
    public function decode(ConfigMatchCandidate $candidate, ConfigItemInterface $config): ProcessorDecodeResultInterface
    {
        // get the raw condition config merged with config
        $condition = $this->pageDataProvider->resolveCandidateToCondition($candidate, $config);
        // load Page Data from DB
        $pageData = $this->pageDataProvider->getPageData($condition, $config);
        if ($pageData instanceof PageData) {
            // load Site and language base path
            $basePath = $this->siteResolver->getSiteBaseUri($pageData->getUid(), $pageData->getLanguageId());

            // concat the path segments to a complete path
            return new ProcessorDecodeResult(Path::join($basePath, $pageData->getSlug()));
        }

        // page not found
        throw new ShortNrNotFoundException();
    }
}
