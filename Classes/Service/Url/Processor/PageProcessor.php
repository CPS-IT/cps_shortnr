<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\DataProvider\PageDataProvider;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\SiteResolverInterface;
use CPSIT\ShortNr\Traits\ValidateUriTrait;
use TypedPatternEngine\Compiler\MatchResult;

class PageProcessor implements ProcessorInterface
{
    use ValidateUriTrait;

    public function __construct(
        protected readonly PageDataProvider $pageDataProvider,
        protected readonly SiteResolverInterface $siteResolver
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
     * @param ConfigItemInterface $configItem
     * @param MatchResult $matchResult
     * @return string|null
     * @throws ShortNrNotFoundException
     */
    public function decode(ConfigItemInterface $configItem, MatchResult $matchResult): ?string
    {
        $conditions = $matchResult->toArray();
        unset($conditions['input']);
        return $this->pageDataProvider->getPageData($conditions, $configItem);
    }
}
