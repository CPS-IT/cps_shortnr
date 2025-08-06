<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\DataProvider;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Exception\ShortNrProcessorException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Exception\ShortNrTreeProcessorException;
use CPSIT\ShortNr\Service\DataProvider\DTO\PageData;
use CPSIT\ShortNr\Service\PlatformAdapter\DTO\TreeProcessor\TreeProcessorResultItemInterface;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\Typo3PageTreeResolver;
use CPSIT\ShortNr\Service\Url\Condition\ConditionService;
use CPSIT\ShortNr\Service\Url\Condition\DTO\ConfigMatchCandidate;
use Throwable;

class PageDataProvider
{
    public function __construct(
        private readonly Typo3PageTreeResolver $pageTreeResolver,
        private readonly ShortNrRepository $shortNrRepository,
        private readonly ConditionService $conditionService,
    )
    {}

    /**
     * @param array $condition
     * @param ConfigItemInterface $configItem
     * @return PageData|null
     * @throws ShortNrNotFoundException
     */
    public function getPageData(array $condition, ConfigItemInterface $configItem): ?PageData
    {
        try {
            $slugKey = $configItem->getValue('slug');
            $uidKey = $configItem->getRecordIdentifier();
            $languageKey = $configItem->getLanguageField();
            $data = $this->shortNrRepository->resolveTable([$slugKey, $uidKey, $languageKey], $configItem->getTableName(), $condition);

            // first active page is our winner!
            foreach ($data as $item) {
                if (isset($item[$uidKey]) && isset($item[$slugKey]) && isset($item[$languageKey])) {
                    if ($this->isPageAvailable((int)$item[$uidKey])) {
                        return new PageData(
                            (int)$item[$uidKey],
                            (int)$item[$languageKey],
                            (string)$item[$slugKey]
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            throw new ShortNrNotFoundException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
    }

    /**
     * @param ConfigMatchCandidate $candidate
     * @param ConfigItemInterface $configItem
     * @return array
     * @throws ShortNrNotFoundException
     */
    public function resolveCandidateToCondition(ConfigMatchCandidate $candidate, ConfigItemInterface $configItem): array
    {
        try {
            $condition = $this->conditionService->resolveConditionToArray($candidate, $configItem);
            return $this->handleLanguageOverlay($condition, $configItem);
        } catch (Throwable $e) {
            throw new ShortNrNotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array $condition
     * @param ConfigItemInterface $configItem
     * @return array
     */
    private function handleLanguageOverlay(array $condition, ConfigItemInterface $configItem): array
    {
        if (!$configItem->canLanguageOverlay())
            return $condition;

        if (($uidField =$configItem->getRecordIdentifier()) !== null && isset($condition[$uidField])) {
            $uidValue = (int)$condition[$uidField];
        } else {
            return $condition;
        }

        $languageValue = null;
        if (($languageField =$configItem->getLanguageField()) !== null && isset($condition[$languageField])) {
            $languageValue = (int)$condition[$languageField];
        }

        try {
            $pageTree = $this->pageTreeResolver->getPageTree();
            if ($pageTree->isMultiTreeLanguageSetup()) {
                return $condition;
            }
        } catch (Throwable) {
            return $condition;
        }


        $item = $pageTree->getItem($uidValue);
        if ($languageValue !== null) {
            $condition[$uidField] = $item->getTranslation($languageValue)->getPrimaryId() ?? $uidValue;
        } else {
            $condition[$uidField] = $item->getBaseTranslation()->getPrimaryId();
        }

        return $condition;
    }

    /**
     * @param int $uid
     * @return bool
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     * @throws ShortNrTreeProcessorException
     */
    private function isPageAvailable(int $uid): bool
    {
        return ($this->pageTreeResolver->getPageTree()->getItem($uid) instanceof TreeProcessorResultItemInterface);
    }
}
