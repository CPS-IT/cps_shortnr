<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Middleware;

use CPSIT\ShortNr\Service\Url\DecoderService;
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
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->decoderService->isShortNrRequest($request)) {
            // process and return redirect result to real url (move permanent)
            // URI is placeholder for now will be replaced with decoder service later
            return ((new RedirectResponse($this->decoderService->decodeRequest($request),302))->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate'));
        }

        return $handler->handle($request);
    }
}
