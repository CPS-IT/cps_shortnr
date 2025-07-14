<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url;

use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Config\ConfigLoader;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractUrlService
{
    public function __construct(
        private readonly ConfigLoader $configLoader,
    )
    {}

    protected function getConfig(): ConfigInterface
    {
        return $this->configLoader->getConfig();
    }

    public function isShortNrRequest(ServerRequestInterface $request): bool
    {
        return $this->isShortNr($request->getUri()->getPath());
    }

    /**
     * @param string $uri
     * @return bool
     */
    public function isShortNr(string $uri): bool
    {
        $config = $this->configLoader->getConfig();

        return false;
    }
}
