<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Language;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PageTreeResolverInterface;

class LanguageOverlayService
{
    public function __construct(
        private readonly PageTreeResolverInterface $pageTreeResolver,
        private readonly ShortNrRepository $repository
    ) {}

    /**
     * @param ConfigItemInterface $configItem
     * @return ConfigItemInterface
     */
    public function resolveLanguageOverlay(ConfigItemInterface $configItem): ConfigItemInterface
    {
        // special pages optimization
        if ($configItem->getTableName() === 'pages') {
            //return $this->overlayPage($configItem);
        }

        return $this->overlayGeneric($configItem);
    }

    /**
     * @param ConfigItemInterface $configItem
     * @return ConfigItemInterface
     */
    private function overlayPage(ConfigItemInterface $configItem): ConfigItemInterface
    {
        $conditions = $configItem->getConditions();
        $idFieldCondition = $conditions[$configItem->getRecordIdentifier() ?? ''] ?? null;

        // we only support non-nested conditions

        $uidMatch = $idFieldCondition?->getMatches()[0] ?? null;

        if ($uidMatch === null || !$uidMatch->isInitialized()) {
            return $configItem;
        }

        // resolve page
        $uid = (int)$uidMatch->getValue();
        $pageTree = $this->pageTreeResolver->getPageTree();
        if ($pageTree->isMultiTreeLanguageSetup() || ($pageItem = $pageTree->getItem($uid)) === null) {
            // skip multi tree setups
            return $configItem;
        }

        // language resolving
        $languageFieldCondition = $conditions[$configItem->getLanguageField() ?? ''] ?? null;
        // we only support non-nested conditions
        $langIdMatch = $languageFieldCondition?->getMatches()[0] ?? null;
        if ($langIdMatch === null || !$this->isPageSupportLanguageUid($uid, (int)$langIdMatch->getValue())) {
            $langId = $this->getPageDefaultLanguageIdForPageUid($uid);
        } else {
            $langId = (int)$langIdMatch->getValue();
        }

        $currentLanguagePageItem = $pageItem->getTranslation($langId);
        $uidMatch->setValue($currentLanguagePageItem?->getPrimaryId());
        $langIdMatch?->setValue($currentLanguagePageItem?->getLanguageId());

        return $configItem;
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
     * @param ConfigItemInterface $configItem
     * @return ConfigItemInterface
     */
    private function overlayGeneric(ConfigItemInterface $configItem): ConfigItemInterface
    {
        $conditions = $configItem->getConditions();

        // UID extraction
        $idFieldCondition = $conditions[$configItem->getRecordIdentifier() ?? ''] ?? null;
        $uidMatch = $idFieldCondition?->getMatches()[0] ?? null;
        if ($uidMatch === null) {
            return $configItem;
        }
        $uid = (int)$uidMatch->getValue();

        // lang ID extraction
        $languageFieldCondition = $conditions[$configItem->getLanguageField() ?? ''] ?? null;
        $langIdMatch = $languageFieldCondition?->getMatches()[0] ?? null;
        if (!($langIdMatch?->isInitialized() ?? false)) {
            // default language
            $langId = 0;
        } else {
            $langId = (int)($langIdMatch?->getValue());
        }

        $table = $configItem->getTableName();
        $uidField = $configItem->getRecordIdentifier();
        $languageField = $configItem->getLanguageField();
        $languageParentField = $configItem->getLanguageParentField();
        if (!$table || !$languageParentField || !$languageField) {
            return $configItem;
        }

        try {
            $resolvement = $this->repository->resolveCorrectUidWithLanguageUid($table, $uidField, $languageField, $languageParentField, $uid);
        } catch (ShortNrQueryException) {
            return $configItem;
        }

        $resolvedUid = $resolvement[$langId] ?? null;

        if ($resolvedUid === null) {
            // language not available -> fall back to base (0)
            $resolvedUid = $resolvement[0] ?? $uid;
            $langIdMatch?->setValue(0);        // update the regex match as well
        } else {
            $langIdMatch?->setValue($langId);
        }
        $uidMatch->setValue($resolvedUid);

        return $configItem;
    }
}
