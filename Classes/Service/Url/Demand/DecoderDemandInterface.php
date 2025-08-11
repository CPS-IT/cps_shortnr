<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use CPSIT\ShortNr\Config\DTO\FieldCondition;
use CPSIT\ShortNr\Service\Url\Regex\MatchResult;
use TYPO3\CMS\Scheduler\Domain\Repository\SchedulerTaskRepository;

interface DecoderDemandInterface extends DemandInterface
{
    /**
     * clean and sanitized ShortNr
     *
     * @return string
     */
    public function getShortNr(): string;

    /**
     * @return ?MatchResult
     */
    public function getMatchResult(): ?MatchResult;

    /**
     * @param MatchResult $matchResult
     * @return void
     */
    public function setMatchResult(MatchResult $matchResult): void;

    /**
     * get resolved conditions
     *
     * @return array<string, FieldCondition>
     */
    public function getConditions(): array;
}
