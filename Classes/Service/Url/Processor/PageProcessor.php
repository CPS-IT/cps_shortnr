<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;

class PageProcessor extends BaseProcessor
{
    private const ConditionSlug = 'slug';

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
        // language overlay ... swap uid with language parent uid and vice versa
        if ($config->canLanguageOverlay($name)) {
            $identifierField = $config->getRecordIdentifier($name);
            $pid = (int)($condition[$identifierField] ?? 0);
            // page id must exist in the conditions
            if ($pid > 0) {
                $condition = $this->mutateConditionForLanguageOverlay($name, $config, $condition, $pid);
            }
        }

        $slug = $config->getValue($name, self::ConditionSlug);
        if (!$slug)
            throw new ShortNrConfigException("'".self::ConditionSlug."' config field not found");

        $result = $this->shortNrRepository->resolveTable(
            [$slug],
            $config->getTableName($name),
            $condition
        );

        return null;

    }
}
