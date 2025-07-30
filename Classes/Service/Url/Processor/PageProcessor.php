<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Exception\ShortNrProcessorException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;

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
     * @return string|null
     * @throws ShortNrConfigException|ShortNrQueryException|ShortNrCacheException
     */
    public function decode(string $uri, string $name, ConfigInterface $config, array $matches): ?string
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
            [$slug],
            $config->getTableName($name),
            $condition
        );

        return null;

    }
}
