<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Service\Url\Demand\Encode\EncoderDemandInterface;

trait CommonTrait
{
    /**
     * @param array $pageRecord
     * @param EncoderDemandInterface $demand
     * @param ConfigItemInterface $configItem
     * @param array $requiredFields
     * @return array
     * @throws ShortNrCacheException
     * @throws ShortNrQueryException
     */
    private function populateMissingRequiredFields(array $pageRecord, EncoderDemandInterface $demand, ConfigItemInterface $configItem, array $requiredFields): array
    {
        $existingFields = array_keys($pageRecord);
        $missingFields = array_diff($requiredFields, $existingFields);

        // no missing fields
        if (empty($missingFields)) {
            return $pageRecord;
        }

        $uidField = $configItem->getRecordIdentifier();

        $uid = $pageRecord[$uidField] ?? null;
        if ($uid === null) {
            return [];
        }

        $languageField = $configItem->getLanguageField();
        $parentField = $configItem->getLanguageParentField();

        $value = $this->repository->loadMissingFields([$languageField, ...$missingFields], $uidField, $languageField, $parentField, $uid, $demand->getLanguageId(), $configItem->getTableName());
        if (empty($value)) {
            return [];
        }

        return $pageRecord + $value;
    }
}
