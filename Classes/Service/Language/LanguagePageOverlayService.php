<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Language;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Domain\DTO\TreeProcessor\TreeProcessorResultInterface;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrTreeProcessorException;
use Throwable;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;

class LanguagePageOverlayService
{
    /**
     * @var array<string, TreeProcessorResultInterface>|null
     */
    private ?array $pageTreeCache = null;

    public function __construct(
        private readonly ShortNrRepository $shortNrRepository,
        private readonly CacheManager $cacheManager,
        private readonly SiteFinder $siteFinder
    )
    {}

    /**
     * @param ConfigInterface $config
     * @param string $name
     * @param int $pageUid
     * @return string
     * @throws ShortNrTreeProcessorException
     * @throws SiteNotFoundException
     */
    public function getLanguageBaseUriForPage(ConfigInterface $config, string $name, int $pageUid): string
    {
        $idField = $config->getRecordIdentifier($name);
        $languageField = $config->getLanguageField($name);
        $languageParentField = $config->getLanguageParentField($name);

        $result = $this->getPageTree(
            indexField: $idField,
            parentPageField: 'pid',
            languageField:  $languageField,
            languageParentField:  $languageParentField
        );

        // pageUid given load correct page item
        $item = $result->getItem($pageUid);
        if ($item === null) {
            throw new ShortNrTreeProcessorException('Page (uid:'.$pageUid.') not found in PageTree.');
        }
        $languageId = $item->getLanguageId();
        $rootPage = $item->getRoot();
        $rootPageId = $rootPage->getPrimaryId();

        try {
            $language = $this->siteFinder->getSiteByRootPageId($rootPageId)->getLanguageById($languageId);
        } catch (Throwable) {
            // slow fallback
            $language = $this->siteFinder->getSiteByPageId($pageUid)->getLanguageById($languageId);
        }


        return $language->getBase()->getPath();
    }

    /**
     * Handle the typo3 Page Overlay system. that graceful accept any overlay uid + language combination and resolve it to the correct uid
     *
     * @param array $condition
     * @param int $pageUid
     * @param string $name
     * @param ConfigInterface $config
     * @return array
     * @throws ShortNrTreeProcessorException
     */
    public function overlayCondition(array $condition, int $pageUid, string $name, ConfigInterface $config): array
    {
        // get the identifier for that type/config/name
        $idField = $config->getRecordIdentifier($name);
        $languageField = $config->getLanguageField($name);
        $languageParentField = $config->getLanguageParentField($name);

        // all fields must be or language overlay is skipped
        if (empty($idField) || empty($languageField) || empty($languageParentField)) {
            return $condition;
        }

        $result = $this->getPageTree(
            indexField: $idField,
            parentPageField: 'pid',
            languageField:  $languageField,
            languageParentField:  $languageParentField
        );

        if ($result->isMultiTreeLanguageSetup()) {
            // we have for each language one Page-Tree
            // nothing to do here no overlay needed
            return $condition;
        }

        // the language Flag in short URL always wins
        // we have a overlay system where trees have multiple Languages
        $item = $result->getItem($pageUid);
        // fallback to base language since we don't have a language flag.
        $languageId = $condition[$languageField] ?? $item?->getBaseTranslation()->getLanguageId();
        // no language change possible, maybe throw exception here?
        // no matching page with uid / language ID in any constellation found
        if ($languageId === null || ($normalizedItem = $item?->getTranslation((int)$languageId)) === null) {
            return $condition;
        }

        // for now, we only support EQUAL operations for overlay systems... maybe later more if we need to
        if (isset($condition[$idField])) {
            $condition[$idField] = $normalizedItem->getPrimaryId();
        }

        return $condition;
    }

    /**
     * load Cached version of PageTree
     *
     * @param string $indexField
     * @param string $parentPageField
     * @param string $languageField
     * @param string $languageParentField
     * @return TreeProcessorResultInterface
     * @throws ShortNrTreeProcessorException
     */
    private function getPageTree(string $indexField, string $parentPageField, string $languageField, string $languageParentField): TreeProcessorResultInterface
    {
        $key = md5($indexField.$parentPageField.$languageField.$languageParentField);
        if (($this->pageTreeCache[$key] ?? null) instanceof TreeProcessorResultInterface) {
            return $this->pageTreeCache[$key];
        }

        try {
            $serializedData = $this->cacheManager->getType3CacheValue(
                'typo3PageTree_'. $key,
                fn(): ?string => serialize($this->shortNrRepository->getPageTreeData($indexField, $parentPageField, $languageField, $languageParentField)),
                ttl: 1200 // 20min
            );
            if (is_string($serializedData)) {
                return $this->pageTreeCache[$key] = unserialize($serializedData);
            }
            throw new ShortNrTreeProcessorException('Unserialized Page Tree Failed');
        } catch (Throwable $e) {
            throw new ShortNrTreeProcessorException('Could not load Page Tree', previous: $e);
        }
    }
}
