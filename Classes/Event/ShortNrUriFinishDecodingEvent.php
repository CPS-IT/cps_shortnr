<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Event;

use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;

class ShortNrUriFinishDecodingEvent
{
    public function __construct(
        private readonly DecoderDemandInterface $demand,
        private ?string $uri,
        private readonly bool $notfound = false,
    )
    {}

    /**
     * @return DecoderDemandInterface
     */
    public function getDemand(): DecoderDemandInterface
    {
        return $this->demand;
    }

    /**
     * @return string|null
     */
    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * @param string|null $uri
     */
    public function setUri(?string $uri): void
    {
        $this->uri = $uri;
    }

    /**
     * @return bool
     */
    public function isNotfound(): bool
    {
        return $this->notfound;
    }
}
