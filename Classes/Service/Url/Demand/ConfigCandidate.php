<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use TypedPatternEngine\Compiler\MatchResult;

class ConfigCandidate implements ConfigCandidateInterface
{
    public function __construct(
        private readonly ConfigItemInterface $configItem,
        private readonly MatchResult $matchResult
    )
    {}

    /**
     * @return ConfigItemInterface
     */
    public function getConfigItem(): ConfigItemInterface
    {
        return $this->configItem;
    }

    /**
     * @return MatchResult
     */
    public function getMatchResult(): MatchResult
    {
        return $this->matchResult;
    }
}
