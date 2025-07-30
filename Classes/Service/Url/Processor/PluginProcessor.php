<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\ConfigInterface;

class PluginProcessor extends AbstractProcessor
{
    /**
     * @return string
     */
    public function getType(): string
    {
        return 'plugin';
    }

    public function decode(string $uri, string $name, ConfigInterface $config, array $matches): ?string
    {
        return null;
    }
}
