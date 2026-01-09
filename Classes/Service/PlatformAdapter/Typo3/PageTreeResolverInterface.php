<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\PlatformAdapter\Typo3;

use CPSIT\ShortNr\Service\PlatformAdapter\DTO\TreeProcessor\TreeProcessorResultInterface;

interface PageTreeResolverInterface
{
    public function getPageTree(): TreeProcessorResultInterface;
}
