<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\ConfigInterface;

interface ProcessorInterface
{
    /**
     * return the type that is matched with the config
     *
     * @return string
     */
    public function getType(): string;

    /**
     * @param string $uri
     * @param string $name
     * @param ConfigInterface $config
     * @param array $matches
     * @return string|null
     */
    public function decode(string $uri, string $name, ConfigInterface $config, array $matches): ?string;
}
