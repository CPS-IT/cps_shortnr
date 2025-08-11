<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Language;

use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PageTreeResolverInterface;
use CPSIT\ShortNr\Service\Url\Regex\MatchResult;

class LanguageOverlayService
{
    public function __construct(
        private readonly PageTreeResolverInterface $pageTreeResolver,
        private readonly ShortNrRepository $repository
    ) {}

    public function resolveLanguageOverlay(MatchResult $matchResult): MatchResult
    {
        // special pages optimization
        if ($matchResult->getConfigItem()->getTableName() === 'pages') {
            return $this->overlayPage($matchResult);
        }

        return $this->overlayGeneric($matchResult);
    }

    /**
     * @param MatchResult $matchResult
     * @return MatchResult
     */
    private function overlayPage(MatchResult $matchResult): MatchResult
    {
        $idFieldCondition = $matchResult->getIdentifierFieldCondition();
        $languageFieldCondition = $matchResult->getLanguageFieldCondition();

        $uidMatch = $idFieldCondition?->getMatches()[0] ?? null;
        if ($uidMatch === null) {
            return $matchResult;
        }

        $uid = (int)$uidMatch->getValue();
        $pageTree = $this->pageTreeResolver->getPageTree();
        if ($pageTree->isMultiTreeLanguageSetup() || ($pageItem = $pageTree->getItem($uid)) === null) {
            // skip multi tree setups
            return $matchResult;
        }

        $langIdMatch = $languageFieldCondition?->getMatches()[0] ?? null;
        if ($langIdMatch === null || !$this->isPageSupportLanguageUid($uid, (int)$langIdMatch->getValue())) {
            $langId = $this->getPageDefaultLanguageIdForPageUid($uid);
        } else {
            $langId = (int)$langIdMatch->getValue();
        }
        $currentLanguagePageItem = $pageItem->getTranslation($langId);
        $uidMatch->setValue($currentLanguagePageItem?->getPrimaryId());
        $langIdMatch?->setValue($currentLanguagePageItem?->getLanguageId());

        return $matchResult;
    }

    /**
     * @param int $pageUid
     * @return int|null
     */
    private function getPageDefaultLanguageIdForPageUid(int $pageUid): ?int
    {
        return $this->pageTreeResolver->getPageTree()->getItem($pageUid)?->getBaseTranslation()->getLanguageId();
    }

    /**
     * @param int $pageUid
     * @param int $languageUid
     * @return bool
     */
    private function isPageSupportLanguageUid(int $pageUid, int $languageUid): bool
    {
        return ($this->pageTreeResolver->getPageTree()->getItem($pageUid)?->getTranslation($languageUid) !== null);
    }

    /**
     * @param MatchResult $matchResult
     * @return MatchResult
     */
    private function overlayGeneric(MatchResult $matchResult): MatchResult
    {
        $idFieldCondition = $matchResult->getIdentifierFieldCondition();
        $languageFieldCondition = $matchResult->getLanguageFieldCondition();

        $uidMatch = $idFieldCondition?->getMatches()[0] ?? null;
        if ($uidMatch === null) {
            return $matchResult;
        }
        $uid = (int)$uidMatch->getValue();
        $langIdMatch = $languageFieldCondition?->getMatches()[0] ?? null;
        if ($langIdMatch?->getValue() === null) {
            // default language
            $langId = 0;
        } else {
            $langId = (int)($langIdMatch?->getValue());
        }

        $table = $matchResult->getConfigItem()->getTableName();
        $uidField = $matchResult->getConfigItem()->getRecordIdentifier();
        $languageField = $matchResult->getConfigItem()->getLanguageField();
        $languageParentField = $matchResult->getConfigItem()->getLanguageParentField();
        if (!$table || !$languageParentField || !$languageField) {
            return $matchResult;
        }

        try {
            $resolvement = $this->repository->resolveCorrectUidWithLanguageUid($table, $uidField, $languageField, $languageParentField, $uid);
        } catch (ShortNrQueryException) {
            return $matchResult;
        }

        $resolvedUid = $resolvement[$langId] ?? null;

        if ($resolvedUid === null) {
            // language not available -> fall back to base (0)
            $resolvedUid = $resolvement[0] ?? $uid;
            $langIdMatch?->setValue(0);        // update the regex match as well
        }

        $uidMatch->setValue($resolvedUid);

        return $matchResult;
    }
}
