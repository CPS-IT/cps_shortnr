<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use Psr\Http\Message\ServerRequestInterface;

class RequestDecoderDemand extends DecoderDemand implements DecoderDemandInterface
{
    private ?ServerRequestInterface $request = null;

    public static function makeFromRequest(ServerRequestInterface $request): DecoderDemandInterface
    {
        return (new static(static::normalizeShortNrUri($request->getUri()->getPath())))->setRequest($request);
    }

    protected function setRequest(ServerRequestInterface $request): static
    {
        $this->request = $request;
        return $this;
    }

    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }
}
