<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Middleware;

use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrDemandNormalizationException;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\DecoderService;
use CPSIT\ShortNr\Service\Url\Demand\DecoderDemandInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;

class ShortNumberMiddleware implements MiddlewareInterface
{
    /**
     * @param DecoderService $decoderService
     */
    public function __construct(
        private readonly DecoderService $decoderService,
    ) {}

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws ShortNrCacheException
     * @throws ShortNrNotFoundException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // this verifies if the request is a valid shortNr request
        // we only support GET method currently to reduce extra load
        if ($request->getMethod() === 'GET' && ($demand = $this->decoderService->getDecoderDemandFromRequest($request)) instanceof DecoderDemandInterface) {
            // process and return redirect result to real url (move permanent)
            $realUri = $this->decoderService->decode($demand);
            if ($realUri !== null) {

                return new RedirectResponse($realUri, 301, [
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'X-Robots-Tag' => 'noindex, nofollow'
                ]);
            }
        }

        return $handler->handle($request);
    }
}
