<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Exception\ShortNrProcessorException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;
use CPSIT\ShortNr\Exception\ShortNrTreeProcessorException;
use CPSIT\ShortNr\Service\Url\Processor\DTO\ProcessorDecodeResult;
use CPSIT\ShortNr\Service\Url\Processor\DTO\ProcessorDecodeResultInterface;
use Symfony\Component\Filesystem\Path;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;

class PageProcessor extends AbstractProcessor
{
    private const ConditionSlug = 'slug';

    /**
     * @return string
     */
    public function getType(): string
    {
        return 'page';
    }

    /**
     * @param string $uri
     * @param string $name
     * @param ConfigInterface $config
     * @param array $matches
     * @return ProcessorDecodeResultInterface
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     * @throws ShortNrProcessorException
     * @throws ShortNrQueryException
     * @throws ShortNrTreeProcessorException
     * @throws SiteNotFoundException
     */
    public function decode(string $uri, string $name, ConfigInterface $config, array $matches): ProcessorDecodeResultInterface
    {
        $condition = $this->mapCondition($config->getCondition($name), $matches);
        $idField = $config->getRecordIdentifier($name);
        $requestedPageUid = $condition[$idField] ?? null;
        if ($requestedPageUid === null) {
            throw new ShortNrProcessorException('Page Uid could not be determined: ' . $uri);
        }

        $condition = $this->languageOverlayService->overlayCondition($condition, $requestedPageUid, $name, $config);
        $slug = $config->getValue($name, self::ConditionSlug);
        if (!$slug) {
            throw new ShortNrConfigException("'".self::ConditionSlug."' config field not found");
        }

        $result = $this->shortNrRepository->resolveTable(
            [$idField, $slug],
            $config->getTableName($name),
            $condition
        );

        if (isset($result[$idField]) && isset($result[$slug])) {
            $pageId = (int)$result[$idField];
            try {
                // TODO: implement getBaseUriForPage
                $sitebase = $this->languageOverlayService->getBaseUriForPage($pageId);
                $languageBase = $this->languageOverlayService->getLanguageBaseUriForPage($config, $name, $pageId);
                return new ProcessorDecodeResult(Path::join($sitebase, $languageBase, $result[$slug]));
            } catch (ShortNrTreeProcessorException) {}
        }

        // TODO: resolve NotFound correct ... refactor the decode method and use sub methods that share the same functionality as the normal page decoding
        return new ProcessorDecodeResult($config->getNotFound($name));
    }
}
