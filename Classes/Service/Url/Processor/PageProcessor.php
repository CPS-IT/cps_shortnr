<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Exception\ShortNrQueryException;

class PageProcessor extends BaseProcessor
{
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
     * @throws ShortNrConfigException
     * @throws ShortNrQueryException
     */
    public function decode(string $uri, string $name, ConfigInterface $config, array $matches): ?string
    {
        $condition = $this->mapCondition($config->getCondition($name), $matches);
        $languageParentField = $config->getLanguageParentField($name);
        $tableName = $config->getTableName($name);
        $slug = $config->getValue($name, 'slug');
        if (!$slug)
            throw new ShortNrConfigException("Slug config not found");

        $result = $this->shortNrRepository->resolveTable([$slug],$tableName, $condition, $languageParentField);

        return null;
    }
}
