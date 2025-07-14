<?php declare(strict_types=1);

namespace CPSIT\Shortnr\Middleware;

use CPSIT\Shortnr\Config\ConfigLoader;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;

class ShortNumberMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ConfigLoader $configLoader
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isShortNrRequest($request)) {
            // process and return redirect result to real url (move permanent)
            // URI is placeholder for now will be replaced with decoder service later
            return ((new RedirectResponse('/',302))->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate'));
        }

        return $handler->handle($request);
    }

    private function isShortNrRequest(ServerRequestInterface $request): bool
    {
        $this->configLoader->getConfig();
        return false;
    }
}
