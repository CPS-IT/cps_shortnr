<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;

abstract class BaseProcessor implements ProcessorInterface
{
    public function __construct(
        protected readonly ShortNrRepository $shortNrRepository,
        protected readonly SiteFinder $siteFinder
    )
    {}

    protected function mutateConditionForLanguageOverlay(string $name, ConfigInterface $config, array $condition, int $pageUid): array
    {
        $data = $this->shortNrRepository->getCachedPageTreeData();

        // use page tree root data

        $languageSiteUids = $this->getSiteLanguageUids($pageUid);
        $langField = $config->getLanguageField($name);
        $langFieldValue = $condition[$langField] ?? $languageSiteUids['_default'] ?? null;




        if (empty($languageSiteUids['languages']) || count($languageSiteUids['languages']) === 1) {

            return $condition;
        }


        // default language is 0


        $idField = $config->getRecordIdentifier($name);
        $langField = $config->getLanguageField($name);
        $parentLangField = $config->getLanguageParentField($name);



        return $condition;
    }

    protected function mapCondition(array $condition, array $matches): array
    {
        $result = [];
        foreach ($condition as $key => $value) {
            // support only match by now, performance reasons
            if (preg_match('/{match-(\d+)}/', (string)$value, $m)) {
                $index = (int)($m[1] ?? -1);
                $matchValue = $matches[$index][0] ?? null;
                $result[$key] = $matchValue !== null ? (int)$matchValue : null;
            } else {
                $result[$key] = $value;
            }
        }

        return array_filter($result, fn($value) => $value !== null);
    }

    /**
     * @param int $pageUid
     * @return array
     * @throws SiteNotFoundException
     */
    protected function getSiteLanguageUids(int $pageUid): array
    {
        $languageUids = [];
        $t = microtime(true);
        $site = $this->siteFinder->getSiteByRootPageId(1);
        $t = (microtime(true) - $t) * 1000;
        foreach ($this->siteFinder->getAllSites() as $site) {
            $siteId = $site->getIdentifier();
            $languageUids[$siteId]['_default'] = $site->getDefaultLanguage()->getLanguageId();
            foreach ($site->getLanguages() as $language) {
                $luid = $language->getLanguageId();
                $languageUids[$siteId]['languages'][$luid] = $luid;
            }
        }

        return $languageUids;
    }
}
