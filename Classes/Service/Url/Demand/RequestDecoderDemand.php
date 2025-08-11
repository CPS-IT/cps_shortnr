<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Demand;

use Psr\Http\Message\ServerRequestInterface;

class RequestDecoderDemand extends DecoderDemand
{
    public static function makeFromRequest(ServerRequestInterface $request): DecoderDemandInterface
    {
        return (new static(static::normalizeShortNrUri($request->getUri()->getPath())))->setRequest($request);
    }
}
