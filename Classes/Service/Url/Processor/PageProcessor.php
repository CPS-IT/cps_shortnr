<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Exception\ShortNrProcessorException;
use CPSIT\ShortNr\Service\PlatformAdapter\Typo3\SiteResolverInterface;
use CPSIT\ShortNr\Service\Url\Demand\Encode\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\Demand\Encode\EnvironmentEncoderDemand;
use CPSIT\ShortNr\Service\Url\Demand\Encode\ObjectEncoderDemand;
use CPSIT\ShortNr\Traits\ValidateUriTrait;
use Throwable;
use TypedPatternEngine\Compiler\MatchResult;

class PageProcessor extends AbstractProcessor implements ProcessorInterface
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

    public function encode(ConfigItemInterface $configItem, EncoderDemandInterface $demand): ?string
    {
        try {
            return $configItem->getPattern()->generate(
                $this->getPageData(
                    $demand,
                    $configItem,
                    $this->getRequiredEncodingFields($configItem)
                ),
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function getPageData(EncoderDemandInterface $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        if ($demand instanceof EnvironmentEncoderDemand) {
            $pageRecord = $demand->getPageRecord();
            $langParentField = $configItem->getLanguageParentField();
            $uidField = $configItem->getRecordIdentifier();
            $parent = (int)($pageRecord[$langParentField] ?? null);
            if ($parent === 0) {
                $parent = null;
            }

            if ($demand->getLanguageId() > 0 || $parent > 0) {
                $pageRecord[$uidField] = $parent ?? $pageRecord[$uidField] ?? throw new ShortNrProcessorException('Cannot find ' . $uidField . ' of Page to encode');
            }
            return array_intersect_key($pageRecord, array_fill_keys($requiredFields, true));
        }
        if ($demand instanceof ObjectEncoderDemand) {

        }

        return [];
    }
}
