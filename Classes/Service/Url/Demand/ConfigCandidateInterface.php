<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use TypedPatternEngine\Compiler\MatchResult;

interface ConfigCandidateInterface
{
    /**
     * @return ConfigItemInterface
     */
    public function getConfigItem(): ConfigItemInterface;

    /**
     * @return MatchResult
     */
    public function getMatchResult(): MatchResult;
}
