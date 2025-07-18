<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\ConfigInterface;

class PageProcessor implements ProcessorInterface
{
    public function getType(): string
    {
        return 'page';
    }

    public function decode(string $uri, string $name, ConfigInterface $config, array $matches): ?string
    {
        return null;
    }
}
