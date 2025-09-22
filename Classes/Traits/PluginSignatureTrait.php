<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Traits;

trait PluginSignatureTrait
{
    private function generatePluginSignature(string $extension, string $plugin): string
    {
        return sprintf('tx_%s_%s', strtolower($extension), strtolower($plugin));
    }
}
