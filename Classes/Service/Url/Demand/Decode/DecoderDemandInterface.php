<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand\Decode;

use CPSIT\ShortNr\Service\Url\Demand\ConfigCandidateInterface;
use CPSIT\ShortNr\Service\Url\Demand\DemandInterface;
use Generator;

interface DecoderDemandInterface extends DemandInterface
{
    /**
     * clean and sanitized ShortNr
     *
     * @return string
     */
    public function getShortNr(): string;

    /**
     * @param ConfigCandidateInterface $candidate
     * @return void
     */
    public function addConfigCandidate(ConfigCandidateInterface $candidate): void;

    /**
     * @return Generator<ConfigCandidateInterface>
     */
    public function getCandidates(): Generator;
}
