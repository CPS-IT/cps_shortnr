<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Language;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Exception\ShortNrTreeProcessorException;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PageTreeResolverInterface;
use TypedPatternEngine\Compiler\MatchResult;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

/**
 * Decode: replaces the uid/language of a short number match with the record that should be served.
 *
 * Only records an anonymous visitor can see now count (hidden, starttime, endtime, live workspace).
 * The named record wins if it is visible in the requested language, otherwise the lowest visible uid
 * in that language. No visible translation: the base record in its own language (languageFallback,
 * default) or notFound. Base record (the row without language parent, usually language 0) not
 * visible: notFound.
 */
class LanguageOverlayService
{
    /**
     * Doktypes the frontend cannot render; a short number to them is not found.
     * Links, shortcuts and backend user sections still redirect (the target page decides).
     */
    public const NON_RENDERABLE_DOKTYPES = [
        PageRepository::DOKTYPE_SPACER,
        PageRepository::DOKTYPE_SYSFOLDER,
    ];

    public function __construct(
        private readonly PageTreeResolverInterface $pageTreeResolver,
        private readonly ShortNrRepository $repository
    ) {}

    /**
     * @param ConfigItemInterface $configItem
     * @param MatchResult $result
     * @return MatchResult
     * @throws ShortNrNotFoundException nothing visible to serve
     * @throws ShortNrCacheException|ShortNrTreeProcessorException|ShortNrQueryException page tree unavailable
     */
    public function resolveLanguageOverlay(ConfigItemInterface $configItem, MatchResult $result): MatchResult
    {
        $uidField = $configItem->getRecordIdentifier();
        $languageField = $configItem->getLanguageField();
        $languageParentField = $configItem->getLanguageParentField();
        $table = $configItem->getTableName();
        $uid = $result->get($uidField ?? '');
        if ($uid === null || !$table || !$languageField || !$languageParentField) {
            return $result;
        }

        $isPages = $table === 'pages';
        if ($isPages && $this->pageTreeResolver->getPageTree()->isMultiTreeLanguageSetup()) {
            // one tree per language: pages are not translations of each other, nothing to overlay
            return $result;
        }

        $requestedLanguage = (int)($result->get($languageField) ?? 0);
        try {
            $family = $this->repository->findVisibleLanguageFamily(
                $table,
                $uidField,
                $languageField,
                $languageParentField,
                (int)$uid,
                $isPages ? ['doktype' => self::NON_RENDERABLE_DOKTYPES] : []
            );
        } catch (ShortNrQueryException) {
            // table does not follow the translation pointer pattern, decode the short number as is
            return $result;
        }

        [$resolvedUid, $resolvedLanguage] = $this->chooseVariant($configItem, $family, (int)$uid, $requestedLanguage);

        $newResult = new MatchResult($result->getInput());
        foreach ($result->getGroups() as $name => $group) {
            ['value' => $value, 'type' => $type, 'constraints' => $constraints] = $group;
            $value = match ($name) {
                $uidField => $resolvedUid,
                $languageField => $resolvedLanguage,
                default => $value,
            };
            $newResult->addGroup($name, $value, $type, $constraints ?? []);
        }

        return $newResult;
    }

    /**
     * When the decode answer for this match can change on its own (scheduled start/end of the
     * record or one of its translations); null if nothing is scheduled.
     */
    public function getNextVisibilityChange(ConfigItemInterface $configItem, MatchResult $result, int $now): ?int
    {
        $uidField = $configItem->getRecordIdentifier();
        $languageParentField = $configItem->getLanguageParentField();
        $table = $configItem->getTableName();
        $uid = $result->get($uidField ?? '');
        if ($uid === null || !$table || !$uidField || !$languageParentField) {
            return null;
        }

        try {
            return $this->repository->findNextVisibilityChange($table, $uidField, $languageParentField, (int)$uid, $now);
        } catch (ShortNrQueryException) {
            return null;
        }
    }

    /**
     * In this order:
     * 1. the record the short number names, if visible and in the requested language (or all languages)
     * 2. the lowest visible uid in the requested language
     * 3. an all-languages (-1) base record, served in the requested language
     * 4. languageFallback: the base record in its own language (0, or e.g. 1 for a record that exists
     *    only in English)
     *
     * @param array{baseUid: int|null, rows: list<array{uid: int, language: int}>} $family visible rows, by uid
     * @return array{int, int} [uid, languageId]
     * @throws ShortNrNotFoundException
     */
    private function chooseVariant(ConfigItemInterface $configItem, array $family, int $requestedUid, int $requestedLanguage): array
    {
        $visible = array_column($family['rows'], 'language', 'uid');
        $baseUid = $family['baseUid'];
        if ($baseUid === null || !isset($visible[$baseUid])) {
            // the core does not serve translations of an invisible base record either
            throw new ShortNrNotFoundException('base record not visible');
        }

        $namedLanguage = $visible[$requestedUid] ?? null;
        if ($namedLanguage === $requestedLanguage) {
            return [$requestedUid, $requestedLanguage];
        }
        if ($namedLanguage === -1) {
            return [$requestedUid, $requestedLanguage];
        }

        foreach ($family['rows'] as $row) {
            if ($row['language'] === $requestedLanguage) {
                return [$row['uid'], $requestedLanguage];
            }
        }

        if ($visible[$baseUid] === -1) {
            return [$baseUid, $requestedLanguage];
        }
        if ($configItem->isLanguageFallbackEnabled()) {
            return [$baseUid, $visible[$baseUid]];
        }

        throw new ShortNrNotFoundException('no visible translation for language ' . $requestedLanguage . ', languageFallback disabled');
    }
}
