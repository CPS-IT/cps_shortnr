<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Exception\ShortNrProcessorException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Service\Condition\ConditionService;
use CPSIT\ShortNr\Service\Condition\Operators\DTO\DirectOperatorContext;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\SiteResolverInterface;
use CPSIT\ShortNr\Service\Url\Demand\Encode\ConfigNameEncoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\Encode\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\Demand\Encode\EnvironmentEncoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\Encode\ObjectEncoderDemand;
use CPSIT\ShortNr\Traits\ValidateUriTrait;
use Symfony\Component\Filesystem\Path;
use Throwable;
use TypedPatternEngine\Compiler\MatchResult;
use TYPO3\CMS\Core\Domain\Page;
use TYPO3\CMS\Frontend\Page\PageInformation;

class PageProcessor extends AbstractProcessor implements ProcessorInterface
{
    use ValidateUriTrait, CommonTrait;

    public function __construct(
        protected readonly SiteResolverInterface $siteResolver,
        protected readonly ShortNrRepository $repository,
        protected readonly ConditionService $conditionService
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

        // special type way for language handling
        $originalLanguageId = $conditions[$languageKey];
        if (isset($conditions[$languageKey])) {
            // we add -1 for the fallback languages
            $conditions[$languageKey] = [$conditions[$languageKey], -1];
        }

        try {
            // we need to fetch it since we must include potential other conditions from the configItem
            $rows = $this->repository->resolveTable([$uidKey, $languageKey], $configItem->getTableName(), $conditions + $configItem->getCondition());
        } catch (Throwable) {
            throw new ShortNrNotFoundException();
        }

        foreach ($rows as $row) {
            $uid = $row[$uidKey];
            $language = $row[$languageKey];

            if ($language === -1) {
                // $originalLanguageId come from the $match
                $language = $originalLanguageId;
            }

            try {
                // generate page, or try it, first success wins
                return $this->siteResolver->getUriByPageId($uid, $language);
            } catch (Throwable) {}
        }

        throw new ShortNrNotFoundException();
    }

    /**
     * @param ConfigItemInterface $configItem
     * @param EncoderDemandInterface $demand
     * @return string|null
     */
    public function encode(ConfigItemInterface $configItem, EncoderDemandInterface $demand): ?string
    {
        try {
            $pageData = $this->getPageData(
                $demand,
                $configItem,
                $this->getRequiredEncodingFields($configItem)
            );
            if (empty($pageData)) {
                return null;
            }

            $shortNr = $configItem->getPattern()->generate(
                $pageData
            );

            $uidField = $configItem->getRecordIdentifier();
            $pid = $pageData[$uidField];
            if ($demand->isAbsolute()) {
                // for now, we trust our middleware to handle the language correct and root everything to the root page
                // maybe in the future we enable the correct language base handling via config toggle
                $base = $this->siteResolver->getSiteFullBaseDomain($pid);
            } else {
                $base = $this->siteResolver->getSiteBaseUri($pid);
            }

            return Path::join($base, $shortNr);

        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param EncoderDemandInterface $demand
     * @param ConfigItemInterface $configItem
     * @param array $requiredFields
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    private function getPageData(EncoderDemandInterface $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $data = [];
        if ($demand instanceof EnvironmentEncoderDemand) {
            $data[] = $this->getDataFromPageRecord($demand, $configItem, $requiredFields);
        } elseif ($demand instanceof ObjectEncoderDemand) {
            $data[] = $this->getDataFromPageObject($demand, $configItem, $requiredFields);
        } elseif ($demand instanceof ConfigNameEncoderDemand) {
            $data[] = $this->getPageDataFromUid($demand, $configItem, $requiredFields);
        }

        if (!empty($configItem->getCondition())) {
            $data = $this->conditionService->directFilterCondition(new DirectOperatorContext(
                $data,
                $configItem->getTableName(),
                $configItem->getCondition(),
                $requiredFields
            ));
        }

        foreach ($data as $item) {
            return $item;
        }

        return [];
    }

    /**
     * @param EnvironmentEncoderDemand $demand
     * @param ConfigItemInterface $configItem
     * @param array $requiredFields
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    private function getDataFromPageRecord(EnvironmentEncoderDemand $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $pageRecord = $demand->getPageRecord();
        return $this->processPageDataArray($pageRecord, $demand, $configItem, $requiredFields);
    }

    /**
     * @param ObjectEncoderDemand $demand
     * @param ConfigItemInterface $configItem
     * @param array $requiredFields
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    private function getDataFromPageObject(ObjectEncoderDemand $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $page = $demand->getObject();
        $pageData = [];
        if ($page instanceof Page) {
            $pageData = $page->toArray();
        } elseif ($page instanceof PageInformation) {
            $pageData = $page->getPageRecord();
        }

        return $this->processPageDataArray($pageData, $demand, $configItem, $requiredFields);
    }

    /**
     * @param ConfigNameEncoderDemand $demand
     * @param ConfigItemInterface $configItem
     * @param array $requiredFields
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    private function getPageDataFromUid(ConfigNameEncoderDemand $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $uidField = $configItem->getRecordIdentifier();
        $pageData[$uidField] = $demand->getUid();

        return $this->processPageDataArray($pageData, $demand, $configItem, $requiredFields);
    }

    /**
     * @param array $pageRecord
     * @param EncoderDemandInterface $demand
     * @param ConfigItemInterface $configItem
     * @param array $requiredFields
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    private function processPageDataArray(array $pageRecord, EncoderDemandInterface $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $languageField = $configItem->getLanguageField();
        $pageRecord[$languageField] = $demand->getLanguageId();

        $pageRecord = $this->populateMissingRequiredFields($pageRecord, $demand, $configItem, $requiredFields);
        return array_intersect_key($pageRecord, array_fill_keys($requiredFields, true));
    }
}
