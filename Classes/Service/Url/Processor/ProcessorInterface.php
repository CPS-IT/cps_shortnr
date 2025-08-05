<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\Url\Condition\DTO\ConfigMatchCandidate;
use CPSIT\ShortNr\Service\Url\Processor\DTO\ProcessorDecodeResultInterface;

interface ProcessorInterface
{
    /**
     * return the type that is matched with the config
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Return a ProcessorDecodeResultInterface or throws ShortNrNotFoundException to trigger the notFound Fallback
     *
     * @param ConfigMatchCandidate $candidate
     * @param ConfigItemInterface $config
     * @return ProcessorDecodeResultInterface
     * @throws ShortNrNotFoundException
     */
    public function decode(ConfigMatchCandidate $candidate, ConfigItemInterface $config): ProcessorDecodeResultInterface;
}
