<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Processor;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\Url\Demand\Encode\EncoderDemandInterface;
use TypedPatternEngine\Compiler\MatchResult;

interface ProcessorInterface
{
    /**
     * return the type that is matched with the config
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Return a string (full URL OR URI) or throws ShortNrNotFoundException to trigger the notFound Fallback
     *
     * @param ConfigItemInterface $configItem
     * @param MatchResult $matchResult
     * @return string|null
     * @throws ShortNrNotFoundException
     */
    public function decode(ConfigItemInterface $configItem, MatchResult $matchResult): ?string;

    /**
     * @param ConfigItemInterface $configItem
     * @param EncoderDemandInterface $demand
     * @return string|null
     */
    public function encode(ConfigItemInterface $configItem, EncoderDemandInterface $demand): ?string;
}
