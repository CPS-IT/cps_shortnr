<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Language;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Domain\DTO\TreeProcessor\TreeProcessorResultInterface;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrLanguageException;
use CPSIT\ShortNr\Exception\ShortNrTreeProcessorException;
use Throwable;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

class LanguagePageOverlayService
{
    public function __construct(
        private readonly ShortNrRepository $shortNrRepository,
        private readonly SiteFinder $siteFinder,
        private readonly CacheManager $cacheManager
    )
    {}

    public function overlayCondition(array $condition, int $pageUid, string $name, ConfigInterface $config): array
    {
        // get the identifier for that type/config/name
        $idField = $config->getRecordIdentifier($name);
        $languageField = $config->getLanguageField($name);
        $languageParentField = $config->getLanguageParentField($name);

        // all fields must be or language overlay is skipped
        if (empty($idField) || empty($languageField) || empty($languageParentField)) {
            return $condition;
        }

        $result = $this->getPageTree(
            indexField: $idField,
            parentPageField: 'pid',
            languageField:  $languageField,
            languageParentField:  $languageParentField
        );

        $result->getItem($pageUid)->getAvailableLanguageIds();

        return $condition;
    }

    /**
     * @param string $indexField
     * @param string $parentPageField
     * @param string $languageField
     * @param string $languageParentField
     * @return TreeProcessorResultInterface
     * @throws ShortNrTreeProcessorException
     */
    private function getPageTree(string $indexField, string $parentPageField, string $languageField, string $languageParentField): TreeProcessorResultInterface
    {
        try {
            return $this->shortNrRepository->getPageTreeData(
                indexField: $indexField,
                parentPageField: $parentPageField,
                languageField: $languageField,
                languageParentField: $languageParentField,
            );

            $serializedData = $this->cacheManager->getType3CacheValue(
                'typo3PageTree',
                fn(): ?string => serialize($this->shortNrRepository->getPageTreeData($indexField, $parentPageField, $languageField, $languageParentField)),
                ttl: 3600
            );
            if (is_string($serializedData)) {
                return unserialize($serializedData);
            }
            throw new ShortNrTreeProcessorException('Unserialized Page Tree Failed');
        } catch (Throwable $e) {
            throw new ShortNrTreeProcessorException('Could not load Page Tree', previous: $e);
        }
    }
}
