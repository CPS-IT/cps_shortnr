<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url;

use CPSIT\ShortNr\Config\DTO\ConfigItemInterface;
use CPSIT\ShortNr\Exception\ShortNrCacheException;
use CPSIT\ShortNr\Exception\ShortNrConfigException;
use CPSIT\ShortNr\Exception\ShortNrNotFoundException;
use CPSIT\ShortNr\Service\Url\Condition\DTO\ConfigMatchCandidate;
use Psr\Http\Message\ServerRequestInterface;

class DecoderService extends AbstractUrlService
{
    /**
     * @param ServerRequestInterface $request
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function decodeRequest(ServerRequestInterface $request): ?string
    {
        return $this->decode($request->getUri()->getPath());
    }

    /**
     * @param string $uri
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException
     */
    public function decode(string $uri): ?string
    {
        $shortNr = $this->normalizeShortNrUri($uri);
        // cache for one day
        return $this->cacheManager->getType3CacheValue('decode_'.md5(strtolower($shortNr)), fn() => $this->decodeShortNr($shortNr), 86_400);
    }

    /**
     *
     * @param string $shortNr
     * @return string|null null if no decoder url, string decoded url
     * @throws ShortNrCacheException
     * @throws ShortNrConfigException|ShortNrNotFoundException
     */
    private function decodeShortNr(string $shortNr): ?string
    {
        $config = $this->getConfig();
        $configItem = null;
        $candidate = null;
        $notfound = false;
        foreach ($this->conditionService->findAllMatchConfigCandidates($shortNr, $config) as $candidate) {
            foreach ($candidate->getNames() as $name) {
                // load processor if possible and gives the decode task over with all current available information
                try {
                    $configItem = $config->getConfigItem($name);
                } catch (ShortNrConfigException) {
                    // could not load config for that name, skip processing
                    continue;
                }

                $result = null;
                try {
                    $result = $this->getProcessor($configItem)?->decode($candidate, $configItem);
                } catch (ShortNrNotFoundException) {
                    $notfound = true;
                }

                if ($result) {
                    return $result;
                }
            }
        }

        if ($notfound && $configItem instanceof ConfigItemInterface && $candidate instanceof ConfigMatchCandidate) {
            try {
                return $this->getNotFoundProcessor($configItem)?->decode($candidate, $configItem);
            } catch (ShortNrNotFoundException) {}
        }

        return null;
    }
}
