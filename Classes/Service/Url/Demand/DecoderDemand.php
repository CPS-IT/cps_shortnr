<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use CPSIT\ShortNr\Service\Url\Regex\MatchResult;
use Psr\Http\Message\ServerRequestInterface;

class DecoderDemand extends Demand implements DecoderDemandInterface
{
    protected ?MatchResult $matchResult = null;

    /**
     * @param string $shortNr provide a clean and sanitized shortNr NO URI
     */
    public function __construct(
        protected readonly string $shortNr
    )
    {}

    public static function makeFromRequest(ServerRequestInterface $request): DecoderDemandInterface
    {
        return new static(static::normalizeShortNrUri($request->getUri()->getPath()));
    }

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
     * @return MatchResult|null
     */
    public function getMatchResult(): ?MatchResult
    {
        return $this->matchResult;
    }

    /**
     * @param MatchResult $matchResult
     * @return void
     */
    public function setMatchResult(MatchResult $matchResult): void
    {
        $this->matchResult = $matchResult;
    }

    public function getConditions(): array
    {
        return $this->configItem->getCondition() ?? [];
    }
}
