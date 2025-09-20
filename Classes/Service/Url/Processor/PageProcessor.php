<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\SiteResolverInterface;
use CPSIT\ShortNr\Traits\ValidateUriTrait;
use Throwable;
use TypedPatternEngine\Compiler\MatchResult;

class PageProcessor implements ProcessorInterface
{
    use ValidateUriTrait;

    public function __construct(
        protected readonly SiteResolverInterface $siteResolver,
        protected readonly ShortNrRepository $repository
    )
    {}

    /**
     * @return string
     */
    public function getType(): string
    {
        return 'page';
    }

    /**
     * @param ConfigItemInterface $configItem
     * @param MatchResult $matchResult
     * @return string|null
     * @throws ShortNrNotFoundException
     */
    public function decode(ConfigItemInterface $configItem, MatchResult $matchResult): ?string
    {
        $conditions = $matchResult->toArray();
        unset($conditions['input']);
        $uidKey = $configItem->getRecordIdentifier();
        $languageKey = $configItem->getLanguageField();
        try {
            // we need to fetch it since we must include potential other conditions from the configItem
            $rows = $this->repository->resolveTable([$uidKey, $languageKey], $configItem->getTableName(), $conditions + $configItem->getCondition());
        } catch (Throwable) {
            throw new ShortNrNotFoundException();
        }

        foreach ($rows as $row) {
            $uid = $row[$uidKey];
            $language = $row[$languageKey];
            try {
                // generate page, or try it, first success wins
                return $this->siteResolver->getUriByPageId($uid, $language);
            } catch (Throwable) {}
        }

        throw new ShortNrNotFoundException();
    }
}
