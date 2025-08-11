<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Event;

use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;
use Psr\Http\Message\ServerRequestInterface;

class ShortNrVerifyRequestEvent
{
    private bool $isShortNrRequest = false;

    public function __construct(
        private readonly ServerRequestInterface $request,
        private DecoderDemandInterface $decoderDemand,
    )
    {}

    /**
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @return bool
     */
    public function isShortNrRequest(): bool
    {
        return $this->isShortNrRequest;
    }

    /**
     * @param bool $isShortNrRequest
     */
    public function setIsShortNrRequest(bool $isShortNrRequest): void
    {
        $this->isShortNrRequest = $isShortNrRequest;
    }

    /**
     * @return DecoderDemandInterface
     */
    public function getDecoderDemand(): DecoderDemandInterface
    {
        return $this->decoderDemand;
    }

    /**
     * @param DecoderDemandInterface $decoderDemand
     */
    public function setDecoderDemand(DecoderDemandInterface $decoderDemand): void
    {
        $this->decoderDemand = $decoderDemand;
    }
}
