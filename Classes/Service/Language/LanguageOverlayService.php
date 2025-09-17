<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Language;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PageTreeResolverInterface;
use TypedPatternEngine\Compiler\MatchResult;

class LanguageOverlayService
{
    public function __construct(
        private readonly PageTreeResolverInterface $pageTreeResolver,
        private readonly ShortNrRepository $repository
    ) {}

    /**
     * @param ConfigItemInterface $configItem
     * @param MatchResult $result
     * @return MatchResult
     */
    public function resolveLanguageOverlay(ConfigItemInterface $configItem, MatchResult $result): MatchResult
    {
        // special pages optimization
        if ($configItem->getTableName() === 'pages') {
            return $this->overlayPage($configItem, $result);
        }

        return $this->overlayGeneric($configItem, $result);
    }

    /**
     * @param ConfigItemInterface $configItem
     * @param MatchResult $result
     * @return MatchResult
     */
    private function overlayPage(ConfigItemInterface $configItem, MatchResult $result): MatchResult
    {
        $uid = $result->get($configItem->getRecordIdentifier() ?? '');
        if ($uid === null) {
            return $result;
        }

        $pageTree = $this->pageTreeResolver->getPageTree();
        if ($pageTree->isMultiTreeLanguageSetup() || ($pageItem = $pageTree->getItem($uid)) === null) {
            // skip multi tree setups
            return $result;
        }

        // language resolving
        $langUid = $result->get($configItem->getLanguageField() ?? '');
        if ($langUid === null || !$this->isPageSupportLanguageUid($uid, $langUid)) {
            $langUid = $this->getPageDefaultLanguageIdForPageUid($uid);
        }

        $currentLanguagePageItem = $pageItem->getTranslation($langUid);
        $newResult = new MatchResult($result->getInput());
        foreach ($result->getGroups() as $name => $group) {
            ['value' => $value, 'type' => $type, 'constraints' => $constraints] = $group;
            $value = match ($name) {
                $configItem->getRecordIdentifier() => $currentLanguagePageItem?->getPrimaryId() ?? $value,
                $configItem->getLanguageField() => $currentLanguagePageItem?->getLanguageId() ?? $value
            };
            $newResult->addGroup($name, $value, $type, $constraints ?? []);
        }

        return $newResult;
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
     * @param MatchResult $result
     * @return MatchResult
     */
    private function overlayGeneric(ConfigItemInterface $configItem, MatchResult $result): MatchResult
    {
        $uid = $result->get($configItem->getRecordIdentifier() ?? '');
        if ($uid === null) {
            return $result;
        }

        // lang ID extraction
        $langUid = $result->get($configItem->getLanguageField() ?? '');
        if (!$langUid) {
            // default language
            $langUid = 0;
        }

        $table = $configItem->getTableName();
        $uidField = $configItem->getRecordIdentifier();
        $languageField = $configItem->getLanguageField();
        $languageParentField = $configItem->getLanguageParentField();
        if (!$table || !$languageParentField || !$languageField) {
            return $result;
        }

        try {
            $resolvement = $this->repository->resolveCorrectUidWithLanguageUid($table, $uidField, $languageField, $languageParentField, $uid);
        } catch (ShortNrQueryException) {
            return $result;
        }

        $resolvedUid = $resolvement[$langUid] ?? null;
        if ($resolvedUid === null) {
            // language not available -> fall back to base (0)
            $resolvedUid = $resolvement[0] ?? $uid;
            $langUid = 0;
        }

        $newResult = new MatchResult($result->getInput());
        foreach ($result->getGroups() as $name => $group) {
            ['value' => $value, 'type' => $type, 'constraints' => $constraints] = $group;
            $value = match ($name) {
                $configItem->getRecordIdentifier() => $resolvedUid ?? $value,
                $configItem->getLanguageField() => $langUid ?? $value
            };
            $newResult->addGroup($name, $value, $type, $constraints ?? []);
        }

        return $newResult;
    }
}
