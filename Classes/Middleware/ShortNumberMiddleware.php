<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Middleware;

use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
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
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->decoderService->isShortNrRequest($request)) {
            // process and return redirect result to real url (move permanent)
            $realUri = $this->decoderService->decodeRequest($request);
            if ($realUri)
                return new RedirectResponse($realUri, 301, [
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'X-Robots-Tag' => 'noindex, nofollow'
                ]);
        }

        return $handler->handle($request);
    }
}
