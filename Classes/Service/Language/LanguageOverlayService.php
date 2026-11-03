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
 * Only records an anonymous visitor can see now count (hidden, starttime, endtime). Among visible
 * translations in the requested language the lowest uid wins. No visible translation: the default
 * language record (languageFallback, default) or notFound. Default record not visible: notFound.
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
            $variants = $this->repository->findVisibleLanguageVariants(
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

        [$resolvedUid, $resolvedLanguage] = $this->chooseVariant($configItem, $variants, $requestedLanguage);

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
     * @param array<int, int> $variants [languageId => uid] of the visible variants
     * @return array{int, int} [uid, languageId]
     * @throws ShortNrNotFoundException
     */
    private function chooseVariant(ConfigItemInterface $configItem, array $variants, int $requestedLanguage): array
    {
        if (!isset($variants[0]) && !isset($variants[-1])) {
            // the core does not serve translations of an invisible default record either
            throw new ShortNrNotFoundException('default language record not visible');
        }

        if (isset($variants[$requestedLanguage])) {
            return [$variants[$requestedLanguage], $requestedLanguage];
        }
        if (isset($variants[-1])) {
            // "all languages" record serves the requested language
            return [$variants[-1], $requestedLanguage];
        }
        if ($configItem->isLanguageFallbackEnabled()) {
            return [$variants[0], 0];
        }

        throw new ShortNrNotFoundException('no visible translation for language ' . $requestedLanguage . ', languageFallback disabled');
    }
}
