<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\PlatformAdapter\Typo3;

use CPSIT\ShortNr\Cache\CacheManager;
use CPSIT\ShortNr\Domain\Repository\ShortNrRepository;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Exception\ShortNrTreeProcessorException;
use CPSIT\ShortNr\Service\PlatformAdapter\DTO\TreeProcessor\TreeProcessorArrayData;
use CPSIT\ShortNr\Service\PlatformAdapter\DTO\TreeProcessor\TreeProcessorResultInterface;

class Typo3PageTreeResolver implements PageTreeResolverInterface
{
    // typo3 pages schema information
    private static string  $pagesDB = 'pages';
    private static string  $identifierField = 'uid';
    private static string  $parentField = 'pid';
    private static string  $languageField = 'sys_language_uid';
    private static string  $languageParentField = 'l10n_parent';

    private ?TreeProcessorResultInterface $resultCache = null;

    public function __construct(
        private readonly ShortNrRepository $shortNrRepository,
        private readonly CacheManager $cacheManager
    )
    {}

    /**
     * get the PageTree, load it once
     * @return TreeProcessorResultInterface
     * @throws ShortNrTreeProcessorException|ShortNrQueryException|ShortNrCacheException
     */
    public function getPageTree(): TreeProcessorResultInterface
    {
        if ($this->resultCache instanceof TreeProcessorResultInterface) {
            return $this->resultCache;
        }

        $serializedPageTree = $this->cacheManager->getType3CacheValue(
            cacheKey: 'page_tree',
            processBlock: fn(): string => serialize($this->generatePageTree()),
            ttl: 3600
        );

        if (is_string($serializedPageTree) && !empty($serializedPageTree)) {
            $pageTree = unserialize($serializedPageTree);
            if ($pageTree instanceof TreeProcessorResultInterface) {
                return $this->resultCache = $pageTree;
            }
        }

        throw new ShortNrTreeProcessorException('Failed to load or generate page tree from cache');
    }

    /**
     * uncached version of the PageTree
     *
     * @internal
     * @return TreeProcessorResultInterface
     * @throws ShortNrQueryException
     * @throws ShortNrTreeProcessorException
     */
    private function generatePageTree(): TreeProcessorResultInterface
    {
        return (new TreeProcessorArrayData(
            primaryKey: self::$identifierField,
            relationKey: self::$parentField,
            languageKey: self::$languageField,
            languageRelationKey: self::$languageParentField,
            data: $this->getRawPageData()
        ))->getResult();
    }

    /**
     * Get page Data from Database
     *
     * @internal
     * @return array
     * @throws ShortNrQueryException
     */
    private function getRawPageData(): array
    {
        return $this->shortNrRepository->getPageTreeData(
            tableName: self::$pagesDB,
            indexField: self::$identifierField,
            parentPageField: self::$parentField,
            languageField:self::$languageField ,
            languageParentField: self::$languageParentField,
        );
    }
}
