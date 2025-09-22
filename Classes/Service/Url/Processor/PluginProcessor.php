<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Config\Enums\ConfigEnum;
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
use CPSIT\ShortNr\Traits\PluginSignatureTrait;
use Symfony\Component\Filesystem\Path;
use Throwable;
use TypedPatternEngine\Compiler\MatchResult;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class PluginProcessor extends AbstractProcessor implements ProcessorInterface
{
    use PluginSignatureTrait;

    public function __construct(
        private readonly ShortNrRepository $repository,
        private readonly SiteResolverInterface $siteResolver,
        protected readonly ConditionService $conditionService
    )
    {}

    /**
     * @return string
     */
    public function getType(): string
    {
        return 'plugin';
    }

    /**
     * @param ConfigItemInterface $configItem
     * @param MatchResult $matchResult
     * @return string|null
     * @throws ShortNrNotFoundException
     * @throws ShortNrProcessorException
     */
    public function decode(ConfigItemInterface $configItem, MatchResult $matchResult): ?string
    {
        try {
            return $this->getPluginUrl($configItem, $matchResult);
        }catch (ShortNrProcessorException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ShortNrNotFoundException(previous: $e);
        }
    }

    /**
     * @param ConfigItemInterface $configItem
     * @param EncoderDemandInterface $demand
     * @return string|null
     */
    public function encode(ConfigItemInterface $configItem, EncoderDemandInterface $demand): ?string
    {
        try {
            $data = $this->getPluginData(
                $demand,
                $configItem,
                $this->getRequiredEncodingFields($configItem)
            );
            if (empty($data)) {
                return null;
            }

            $shortNr = $configItem->getPattern()->generate(
                $data
            );

            $pluginPid = $configItem->getPluginConfig()['pid'] ?? throw new ShortNrProcessorException('could not find Plugin Pid');
            if ($demand->isAbsolute()) {
                $base = $this->siteResolver->getSiteFullBaseDomain($pluginPid, $demand->getLanguageId());
            } else {
                $base = $this->siteResolver->getSiteBaseUri($pluginPid, $demand->getLanguageId());
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
    public function getPluginData(EncoderDemandInterface $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $data = [];
        if ($demand instanceof EnvironmentEncoderDemand) {
            $data[] = $this->getDataFromArguments($demand, $configItem, $requiredFields);
        } elseif ($demand instanceof ObjectEncoderDemand) {
            $data[] = $this->getDataFromObject($demand, $configItem, $requiredFields);
        } elseif ($demand instanceof ConfigNameEncoderDemand) {
            $data[] = $this->getDataFromUid($demand, $configItem, $requiredFields);
        }

        if (!empty($configItem->getCondition())) {
            $data = $this->conditionService->directFilterCondition(new DirectOperatorContext(
                $data,
                $configItem->getTableName(),
                $configItem->getCondition(),
                array_keys($data)
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
    private function getDataFromArguments(EnvironmentEncoderDemand $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $pluginConfig = $configItem->getPluginConfig();
        $pluginSignature = $this->generatePluginSignature($pluginConfig['extension'], $pluginConfig['plugin']);
        $pluginObjectName = $pluginConfig['objectName'];

        $extbaseArguments = $demand->getExtbaseRequestParameters()->getArguments();
        if (empty($extbaseArguments)) {
            $extbaseArguments = $demand->getQueryParams()[$pluginSignature] ??
                $demand->getPageRoutingArguments()?->getArguments()[$pluginSignature] ??
                $demand->getPageRoutingArguments()?->getStaticArguments()[$pluginSignature] ??
                $demand->getPageRoutingArguments()?->getDynamicArguments()[$pluginSignature] ??
                $demand->getPageRoutingArguments()?->getRouteArguments()[$pluginSignature] ??
                $demand->getPageRoutingArguments()?->getQueryArguments()[$pluginSignature] ?? [];
        }

        $uidField = $configItem->getRecordIdentifier();
        $languageField = $configItem->getLanguageField();
        $uid = (int)($extbaseArguments[$pluginObjectName] ?? $extbaseArguments[$uidField] ?? 0);
        $languageUid = $demand->getLanguageId();

        $data = [
            $uidField => $uid,
            $languageField => $languageUid
        ];

        return $this->processPluginData($data, $demand, $configItem, $requiredFields);
    }

    /**
     * @param ObjectEncoderDemand $demand
     * @param ConfigItemInterface $configItem
     * @param array $requiredFields
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    private function getDataFromObject(ObjectEncoderDemand $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $object = $demand->getObject();
        $uidField = $configItem->getRecordIdentifier();
        $languageField = $configItem->getLanguageField();
        $languageUid = $demand->getLanguageId();
        if (!($object instanceof AbstractEntity)) {
            return [];
        }

        $data = [
            $uidField => $object->getUid(),
            $languageField => $languageUid
        ];

        $missingFields = array_diff($requiredFields, array_keys($data));
        foreach ($missingFields as $field) {
            if ($object->_hasProperty($field)) {
                $data[$field] = $object->_getProperty($field);
            }
        }

        return $this->processPluginData($data, $demand, $configItem, $requiredFields);
    }

    /**
     * @param ConfigNameEncoderDemand $demand
     * @param ConfigItemInterface $configItem
     * @param array $requiredFields
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    private function getDataFromUid(ConfigNameEncoderDemand $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $uidField = $configItem->getRecordIdentifier();
        $languageField = $configItem->getLanguageField();

        $data = [
            $uidField => $demand->getUid(),
            $languageField => $demand->getLanguageId()
        ];
        return $this->processPluginData($data, $demand, $configItem, $requiredFields);
    }

    /**
     * @param array $pluginData
     * @param EncoderDemandInterface $demand
     * @param ConfigItemInterface $configItem
     * @param array $requiredFields
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    private function processPluginData(array $pluginData, EncoderDemandInterface $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $languageField = $configItem->getLanguageField();
        $pluginData[$languageField] = $demand->getLanguageId();

        $pageRecord = $this->populateMissingRequiredFields($pluginData, $demand, $configItem, $requiredFields);
        return array_intersect_key($pageRecord, array_fill_keys($requiredFields, true));
    }

    /**
     * @param array $pluginData
     * @param EncoderDemandInterface $demand
     * @param ConfigItemInterface $configItem
     * @param array $requiredFields
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    private function populateMissingRequiredFields(array $pluginData, EncoderDemandInterface $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $existingFields = array_keys($pluginData);
        $missingFields = array_diff($requiredFields, $existingFields);

        // no missing fields
        if (empty($missingFields)) {
            return $pluginData;
        }

        $uidField = $configItem->getRecordIdentifier();

        $uid = $pluginData[$uidField] ?? null;
        if ($uid === null) {
            return [];
        }

        $languageField = $configItem->getLanguageField();
        $parentField = $configItem->getLanguageParentField();

        $value = $this->repository->loadMissingFields([$languageField, ...$missingFields], $uidField, $languageField, $parentField, $uid, $demand->getLanguageId(), $configItem->getTableName());
        if (empty($value)) {
            return [];
        }

        return $pluginData + $value;
    }

    /**
     * @param ConfigItemInterface $configItem
     * @param MatchResult $matchResult
     * @return string
     * @throws ShortNrProcessorException
     * @throws ShortNrNotFoundException
     */
    private function getPluginUrl(ConfigItemInterface $configItem, MatchResult $matchResult): string
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

        $pluginConfig = $configItem->getPluginConfig() ?? throw new ShortNrProcessorException('Missing Plugin Processor Config '. ConfigEnum::Plugin->value . ' in config: ' . $configItem->getName());
        $basePid = $pluginConfig['pid'] ?? throw new ShortNrProcessorException('Missing Plugin Processor Config \'pid\'');
        $plugin = $pluginConfig['plugin'] ?? throw new ShortNrProcessorException('Missing Plugin Processor Config \'plugin\'');
        $extension = $pluginConfig['extension'] ?? throw new ShortNrProcessorException('Missing Plugin Processor Config \'extension\'');
        $action = $pluginConfig['action'] ?? throw new ShortNrProcessorException('Missing Plugin Processor Config \'action\'');
        $controller = $pluginConfig['controller'] ?? throw new ShortNrProcessorException('Missing Plugin Processor Config \'controller\'');
        $objectName = $pluginConfig['objectName'] ?? 'uid';
        $pluginSignature = $this->generatePluginSignature($extension, $plugin);

        foreach ($rows as $row) {
            $uid = $row[$uidKey];
            $languageUid = $row[$languageKey];

            $pluginConfig = [
                'id' => $basePid,
                $pluginSignature => [
                    'action' => $action,
                    'controller' => $controller,
                    $objectName => $uid
                ]
            ];
            try {
                return $this->siteResolver->getUriByPageId(
                    $basePid,
                    $languageUid,
                    $pluginConfig
                );
            } catch (Throwable) {}
        }

        throw new ShortNrNotFoundException();
    }
}
