<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\DataProvider\PageDataProvider;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\SiteResolverInterface;
use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;
use CPSIT\ShortNr\Traits\ValidateUriTrait;

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
     * @param DecoderDemandInterface $demand
     * @return string|null
     * @throws ShortNrNotFoundException
     */
    public function decode(DecoderDemandInterface $demand): ?string
    {
        $configItem = $demand->getConfigItem();
        if (!$configItem || empty($conditions = $configItem->getConditions())) {
            return null;
        }

        return $this->pageDataProvider->getPageData($conditions, $configItem);
    }
}
