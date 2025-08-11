<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\DataProvider;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Config\DTO\FieldConditionInterface;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Exception\ShortNrSiteFinderException;
use CPSIT\ShortNr\Service\PlatformAdapter\DTO\TreeProcessor\TreeProcessorResultItemInterface;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\PageTreeResolverInterface;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\SiteResolverInterface;
use Symfony\Component\Filesystem\Path;
use Throwable;

class PageDataProvider
{
    public function __construct(
        private readonly PageTreeResolverInterface $pageTreeResolver,
        private readonly ShortNrRepository $shortNrRepository,
        private readonly SiteResolverInterface $siteResolver
    ) {}

    /**
     * @param array<string, FieldConditionInterface|mixed> $condition
     * @param ConfigItemInterface $configItem
     * @return string|null
     * @throws ShortNrNotFoundException
     */
    public function getPageData(array $condition, ConfigItemInterface $configItem): ?string
    {
        try {
            $slugKey = $configItem->getValue('slug');
            $uidKey = $configItem->getRecordIdentifier();
            $languageKey = $configItem->getLanguageField();
            $rows = $this->shortNrRepository->resolveTable([$slugKey, $uidKey, $languageKey], $configItem->getTableName(), $condition);

            // first active page is our winner!
            foreach ($rows as $row) {
                [
                    $uidKey => $uid,
                    $languageKey => $langId,
                    $slugKey => $slug,
                ] = $row + [$uidKey => null, $languageKey => null, $slugKey => null];

                if ($uid === null || $langId === null || $slug === null) {
                    continue;                       // incomplete row → skip
                }

                $uid    = (int)$uid;
                $langId = (int)$langId;
                $slug   = (string)$slug;

                if ($this->isPageAvailable($uid)) {
                    return $this->appendBasePath($slug, $uid, $langId);
                }
            }
        } catch (Throwable $e) {
            throw new ShortNrNotFoundException($e->getMessage(), $e->getCode(), $e);
        }

        throw new ShortNrNotFoundException();
    }

    /**
     * @param int $uid
     * @return bool
     */
    private function isPageAvailable(int $uid): bool
    {
        return ($this->pageTreeResolver->getPageTree()->getItem($uid) instanceof TreeProcessorResultItemInterface);
    }

    /**
     * @param string $slug
     * @param int $pageUid
     * @param int $languageId
     * @return string
     * @throws ShortNrSiteFinderException
     */
    private function appendBasePath(string $slug, int $pageUid, int $languageId): string
    {
        return Path::join(
            $this->siteResolver->getSiteBaseUri($pageUid, $languageId),
            $slug
        );
    }
}
