<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url;

use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Config\ConfigLoader;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Service\Url\Condition\ConditionService;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractUrlService
{
    public function __construct(
        private readonly ConfigLoader $configLoader,
        protected readonly ConditionService $conditionService,
    )
    {}

    /**
     * @return ConfigInterface
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    protected function getConfig(): ConfigInterface
    {
        return $this->configLoader->getConfig();
    }

    /**
     * @param ServerRequestInterface $request
     * @return bool
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function isShortNrRequest(ServerRequestInterface $request): bool
    {
        return $this->isShortNr($request->getUri()->getPath());
    }

    /**
     * fast check if the given uri is a shortNr
     *
     * @param string $uri uri can be like /PAGE123 or /PAGE123-1 (for english)
     * @return bool
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function isShortNr(string $uri): bool
    {
        $config = $this->configLoader->getConfig();
        return $this->conditionService->matchAny($uri, $config);
    }
}
