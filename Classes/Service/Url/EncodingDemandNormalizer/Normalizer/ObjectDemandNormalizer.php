<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer;

use CPSIT\ShortNr\Config\DTO\ConfigInterface;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Exception\ShortNrDemandNormalizationException;
use CPSIT\ShortNr\Service\Url\Demand\EncoderDemandInterface;
use CPSIT\ShortNr\Service\Url\Demand\ObjectEncoderDemand;
use CPSIT\ShortNr\Service\Url\EncodingDemandNormalizer\Normalizer\DTO\EncoderDemandNormalizationResult;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

class ObjectDemandNormalizer implements EncodingDemandNormalizerInterface
{
    private array $cache = [];

    public function __construct(
        private readonly DataMapper $dataMapper,
    )
    {}

    /**
     * @param EncoderDemandInterface $demand
     * @param ConfigInterface $config
     * @return bool
     */
    public function supports(EncoderDemandInterface $demand, ConfigInterface $config): bool
    {
        return $demand instanceof ObjectEncoderDemand && $demand->getObject() instanceof AbstractEntity;
    }

    /**
     * @param EncoderDemandInterface $demand
     * @param ConfigInterface $config
     * @return EncoderDemandNormalizationResult|null
     * @throws ShortNrDemandNormalizationException
     * @throws ShortNrConfigException
     * @throws Exception
     */
    public function normalize(EncoderDemandInterface $demand, ConfigInterface $config): ?EncoderDemandNormalizationResult
    {
        if (!$demand instanceof ObjectEncoderDemand) {
            return null;
        }

        $object = $demand->getObject();
        $tableName = $this->dataMapper->getDataMap($object::class)->getTableName();
        $configItems = $config->getConfigItemsByTableName($tableName);
        foreach ($configItems as $configItem) {
            $staticData = $this->convertObjectToArray($object, $configItem);
            // no condition static data found, we don't need to look up dynamic data, ONLY static data is relevant
            // Encoding only needs static conditions - type: 1, is_event: 1, category: "tech" etc.
            if (empty($staticData) || $this->conditionService->encodingOperatorCondition($staticData, $configItem->getConditions())) {
                // TODO: load all MATCH-RELEVANT information too we later need to create the shortNr without Database
                return new EncoderDemandNormalizationResult([], $configItem);
            }
        }

        return null;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 50;
    }
}
