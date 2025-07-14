<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url;

use Psr\Http\Message\ServerRequestInterface;

class DecoderService extends AbstractUrlService
{
    /**
     * @param ServerRequestInterface $request
     * @return string|null
     */
    public function decodeRequest(ServerRequestInterface $request): ?string
    {
        return $this->decode($request->getUri()->getPath());
    }

    /**
     * @param string $uri
     * @return string|null
     */
    public function decode(string $uri): ?string
    {
        return null;
    }
}
