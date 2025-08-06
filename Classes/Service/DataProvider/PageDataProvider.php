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
            $candidate = $this->normalizeCandidate($candidate, $configItem);
            return $this->conditionService->resolveConditionToArray($candidate, $configItem);
        } catch (Throwable $e) {
            throw new ShortNrNotFoundException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * we only want the base UID for resolving data
     *
     * @param ConfigMatchCandidate $candidate
     * @param ConfigItemInterface $configItem
     * @return ConfigMatchCandidate
     * @throws ShortNrCacheException
     * @throws ShortNrProcessorException
     * @throws ShortNrQueryException
     * @throws ShortNrTreeProcessorException
     */
    private function normalizeCandidate(ConfigMatchCandidate $candidate, ConfigItemInterface $configItem): ConfigMatchCandidate
    {
        if (!$configItem->canLanguageOverlay())
            return $candidate;

        if (($languageId = $candidate->getValueFromMatchesViaMatchGroupString($configItem->getLanguageField())) !== null) {
            $languageId = (int)$languageId;
        }

        if (($shortNrUid = $candidate->getValueFromMatchesViaMatchGroupString($configItem->getRecordIdentifier())) !== null) {
            $shortNrUid = (int)$shortNrUid;
            $pageTree = $this->pageTreeResolver->getPageTree();
            // no overlay available in multitree systems
            if ($pageTree->isMultiTreeLanguageSetup())
                return $candidate;

            if (($treeItem = $pageTree->getItem($shortNrUid)) === null) {
                throw new ShortNrProcessorException('Page with id ' . $shortNrUid . ' not found');
            }

            if ($languageId !== null) {
                // fallback to base translation
                $targetUid = $treeItem->getTranslation($languageId)?->getPrimaryId();
            } else {
                $targetUid = $treeItem->getBaseTranslation()->getPrimaryId();
            }

            if ($targetUid === null) {
                throw new ShortNrProcessorException('Page with id ' . $shortNrUid . 'has no language child with language id ' . $languageId);
            }

            $uidMatchId = $candidate->extractIdFromMatchGroupPlaceholder($configItem->getRecordIdentifier());
            // base id mismatch with given shortnr uid... replace!
            if ($targetUid !== $shortNrUid) {
                $updatedMatches = $candidate->getMatches();
                $updatedMatches[$uidMatchId][0] = $targetUid;
                return new ConfigMatchCandidate(
                    $candidate->getShortNrUri(),
                    $candidate->getNames(),
                    $updatedMatches
                );
            }
        }

        return $candidate;
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
