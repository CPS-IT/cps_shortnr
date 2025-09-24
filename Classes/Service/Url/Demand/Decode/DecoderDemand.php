<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand\Decode;

use CPSIT\ShortNr\Service\Url\Demand\ConfigCandidateInterface;
use CPSIT\ShortNr\Service\Url\Demand\Demand;
use Generator;

class DecoderDemand extends Demand implements DecoderDemandInterface
{
    private array $candidates = [];

    /**
     * @param string $shortNr provide a clean and sanitized shortNr NO URI
     */
    public function __construct(
        protected readonly string $shortNr
    )
    {}

    /**
     * clean and sanitized ShortNr
     *
     * @return string
     */
    public function getShortNr(): string
    {
        return $this->shortNr;
    }

    /**
     * @inheritDoc
     */
    public function addConfigCandidate(ConfigCandidateInterface $candidate): void
    {
        $this->candidates[$candidate->getConfigItem()->getName()] = $candidate;
    }

    /**
     * @inheritDoc
     */
    public function getCandidates(): Generator
    {
        yield from $this->candidates;
    }

    /**
     * @inheritDoc
     */
    public function getCacheKey(): ?string
    {
        return $this->shortNr;
    }
}
